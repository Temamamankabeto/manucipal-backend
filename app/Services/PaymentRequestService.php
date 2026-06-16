<?php

namespace App\Services;

use App\Models\PaymentAttachment;
use App\Models\PaymentHistory;
use App\Models\PaymentRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentRequestService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min((int)($filters['per_page'] ?? 10), 100));
        return PaymentRequest::query()->with(['requester:id,name,email','currentHandler:id,name,email'])
            ->when($filters['status'] ?? null, fn($q,$v)=>$q->where('status',$v))
            ->when($filters['search'] ?? null, fn($q,$v)=>$q->where(fn($qq)=>$qq->where('request_no','like',"%{$v}%")->orWhere('title','like',"%{$v}%")->orWhere('payee_name','like',"%{$v}%")))
            ->latest()->paginate($perPage);
    }
    public function find(int|string $id): PaymentRequest
    {
        return PaymentRequest::with(['attachments','histories.actor:id,name,email','requester:id,name,email','currentHandler:id,name,email'])->findOrFail($id);
    }
    public function create(array $data, ?array $files=[]): PaymentRequest
    {
        return DB::transaction(function() use($data,$files){
            unset($data['attachments']);
            $data['request_no']=$this->nextNumber('PAY'); $data['requested_by']=auth()->id();
            $request=PaymentRequest::create($data); $this->storeAttachments($request,$files??[]);
            $this->history($request,'CREATE',null,$request->status,'Payment request created');
            AuditLogService::log('payment','CREATE',$request,['after'=>$request->toArray()]);
            return $this->find($request->id);
        });
    }
    public function update(PaymentRequest $request, array $data, ?array $files=[]): PaymentRequest
    {
        return DB::transaction(function() use($request,$data,$files){
            $before=$request->toArray(); unset($data['attachments']); $request->update($data); $this->storeAttachments($request,$files??[]);
            $this->history($request,'UPDATE',$before['status']??null,$request->status,'Payment request updated');
            AuditLogService::log('payment','UPDATE',$request,['before'=>$before,'after'=>$request->fresh()->toArray()]);
            return $this->find($request->id);
        });
    }
    public function action(PaymentRequest $request, string $action, array $data=[]): PaymentRequest
    {
        return DB::transaction(function() use($request,$action,$data){
            $from=$request->status; $to=$this->nextStatus($from,$action); $payload=['status'=>$to];
            if($action==='submit') $payload['submitted_at']=now();
            if($action==='records_process'){ $payload['reference_no']=$data['reference_no']??$request->reference_no??$this->nextNumber('PAY-REF'); $payload['official_date']=$data['official_date']??now()->toDateString(); $payload['voucher_no']=$data['voucher_no']??$request->voucher_no??$this->nextNumber('VOU'); }
            if($to===PaymentRequest::STATUS_COMPLETED) $payload['completed_at']=now();
            $request->update($payload);
            $this->history($request,strtoupper($action),$from,$to,$data['note']??$data['reason']??null,$data);
            AuditLogService::log('payment',strtoupper($action),$request,['before'=>['status'=>$from],'after'=>['status'=>$to],'meta'=>$data]);
            return $this->find($request->id);
        });
    }
    protected function nextStatus(string $from,string $action): string
    {
        if($action==='reject') return $from; // handled by previous-step workflow
        return match($action){
            'submit'=>PaymentRequest::STATUS_MANAGER_REVIEW,
            'manager_approve'=>PaymentRequest::STATUS_BUDGET_TL_REVIEW,
            'budget_tl_approve'=>PaymentRequest::STATUS_BUDGET_EXPERT_PROCESSING,
            'expert_complete'=>PaymentRequest::STATUS_BUDGET_TL_FINAL_REVIEW,
            'budget_tl_final_approve'=>PaymentRequest::STATUS_FINAL_MANAGER_REVIEW,
            'final_manager_approve'=>PaymentRequest::STATUS_RECORDS_PROCESSING,
            'records_process'=>PaymentRequest::STATUS_SENT_TO_FINANCE,
            'finance_complete'=>PaymentRequest::STATUS_COMPLETED,
            default=>$from,
        };
    }
    protected function storeAttachments(PaymentRequest $request,array $files): void
    {
        foreach($files as $file){ if($file instanceof UploadedFile){ $path=$file->store('payments','public'); PaymentAttachment::create(['payment_request_id'=>$request->id,'uploaded_by'=>auth()->id(),'original_name'=>$file->getClientOriginalName(),'stored_path'=>$path,'mime_type'=>$file->getClientMimeType(),'size_bytes'=>$file->getSize()?:0]); }}
    }
    protected function history(PaymentRequest $request,string $action,?string $from,?string $to,?string $note=null,array $meta=[]): void
    { PaymentHistory::create(['payment_request_id'=>$request->id,'actor_id'=>auth()->id(),'action'=>$action,'from_status'=>$from,'to_status'=>$to,'note'=>$note,'metadata'=>$meta]); }
    protected function nextNumber(string $prefix): string { return $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)); }
}
