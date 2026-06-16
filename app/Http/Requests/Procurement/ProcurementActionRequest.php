<?php
namespace App\Http\Requests\Procurement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class ProcurementActionRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['action'=>['required',Rule::in(['submit','manager_approve','asset_team_approve','budget_tl_approve','assign_budget_code','expert_complete','final_manager_approve','records_process','finance_complete','reject'])],'note'=>['nullable','string'],'reason'=>['required_if:action,reject','nullable','string'],'budget_code'=>['required_if:action,assign_budget_code','nullable','string','max:100'],'reference_no'=>['nullable','string','max:100'],'official_date'=>['nullable','date'],'official_date_ec'=>['nullable','string','max:30'],'receiver_type'=>['nullable','string','max:80'],
            'forward_to_role'=>['nullable',Rule::in(['asset_team_leader','machinery_team_leader','asset','machinery','fixed_asset','machinary'])],
            'approval_description'=>['nullable','string','max:2000'],
            'items'=>['nullable','array'],
            'items.*.item_name'=>['required_with:items','string','max:255'],
            'items.*.specification'=>['nullable','string','max:255'],
            'items.*.unit'=>['nullable','string','max:50'],
            'items.*.quantity'=>['required_with:items','numeric','min:0'],
            'items.*.estimated_unit_cost'=>['nullable','numeric','min:0'],
            'items.*.estimated_total_cost'=>['nullable','numeric','min:0'],
        ]; } }
