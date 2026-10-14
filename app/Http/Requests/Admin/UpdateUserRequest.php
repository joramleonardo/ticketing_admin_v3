<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'Super Admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user') instanceof \App\Models\User
            ? $this->route('user')->id
            : $this->route('user');

        return [
            'name' => ['required', 'string', 'max:191'],
            'username' => ['required', 'string', 'max:191', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:users,username,'.$userId],
            'email' => ['nullable', 'string', 'email', 'max:191', 'unique:users,email,'.$userId],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', 'in:Super Admin,Admin,Staff'],
            'isActivated' => ['required', 'in:1,0,true,false'],
            'reassign_action' => ['nullable', 'string', 'in:reassign,unassign,keep'],
            'reassign_to' => ['nullable', 'required_if:reassign_action,reassign', 'string', 'max:255'],
        ];
    }
}
