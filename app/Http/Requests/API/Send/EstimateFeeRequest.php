<?php

namespace App\Http\Requests\API\Send;

use App\Http\Requests\API\Base\APIRequest;
use Illuminate\Http\Exception\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Validation\Validator;
use LinusU\Bitcoin\AddressValidator;

class EstimateFeeRequest extends APIRequest {



    public function getValidatorInstance()
    {
        $validator = parent::getValidatorInstance();

        $validator->after(function () use ($validator)
        {
            // validate destination
            $destination = $this->json('destination');
            if (!AddressValidator::isValid($destination)) {
                $validator->errors()->add('destination', 'The destination was invalid.');
            }
        });

        return $validator;
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'destination'        => 'required',
            'quantity'           => 'numeric|notIn:0',
            'dust_size'          => 'numeric',
            'asset'              => 'required|min:3',
            'account'            => 'max:127',
            'unconfirmed'        => 'boolean',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

}
