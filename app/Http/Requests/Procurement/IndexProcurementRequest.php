<?php
namespace App\Http\Requests\Procurement;
use Illuminate\Foundation\Http\FormRequest;
class IndexProcurementRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['search'=>['nullable','string','max:120'],'status'=>['nullable','string','max:80'],'per_page'=>['nullable','integer','min:1','max:100']]; } }
