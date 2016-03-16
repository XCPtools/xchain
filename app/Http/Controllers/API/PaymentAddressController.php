<?php 

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Base\APIController;
use App\Http\Requests\API\PaymentAddress\CreatePaymentAddressRequest;
use App\Http\Requests\API\PaymentAddress\CreateUnmanagedAddressRequest;
use App\Http\Requests\API\PaymentAddress\UpdatePaymentAddressRequest;
use App\Providers\Accounts\Facade\AccountHandler;
use App\Repositories\PaymentAddressRepository;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tokenly\LaravelApiProvider\Helpers\APIControllerHelper;
use Tokenly\LaravelEventLog\Facade\EventLog;

class PaymentAddressController extends APIController {

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(APIControllerHelper $helper, PaymentAddressRepository $payment_address_respository)
    {
        return $helper->index($payment_address_respository);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(APIControllerHelper $helper, CreatePaymentAddressRequest $request, PaymentAddressRepository $payment_address_respository, Guard $auth)
    {
        return $this->buildNewPaymentAddressFromRequest($helper, $request, $payment_address_respository, $auth, true);
    }

    /**
     * Store a new unmanaged address
     *
     * @return Response
     */
    public function createUnmanaged(APIControllerHelper $helper, CreateUnmanagedAddressRequest $request, PaymentAddressRepository $payment_address_respository, Guard $auth) {
        return $this->buildNewPaymentAddressFromRequest($helper, $request, $payment_address_respository, $auth, false);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show(APIControllerHelper $helper, PaymentAddressRepository $payment_address_respository, $id)
    {
        return $helper->show($payment_address_respository, $id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(APIControllerHelper $helper, UpdatePaymentAddressRequest $request, PaymentAddressRepository $payment_address_respository, $id)
    {
        return $helper->update($payment_address_respository, $id, $request->only(array_keys($request->rules())));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy(APIControllerHelper $helper, PaymentAddressRepository $payment_address_respository, $id)
    {
        return $helper->destroy($payment_address_respository, $id);
    }

    // ------------------------------------------------------------------------

    protected function buildNewPaymentAddressFromRequest(APIControllerHelper $helper, Request $request, PaymentAddressRepository $payment_address_respository, Guard $auth, $is_managed) {
        $user = $auth->getUser();
        if (!$user) { throw new Exception("User not found", 1); }

        $attributes = $request->only(array_keys($request->rules()));
        $attributes['user_id'] = $user['id'];

        $address = $payment_address_respository->create($attributes);

        if ($is_managed) {
            EventLog::log('paymentAddress.created', $address->toArray(), ['uuid', 'user_id', 'address', 'id']);
        } else {
            EventLog::log('unmanagedPaymentAddress.created', $address->toArray(), ['uuid', 'user_id', 'address', 'id']);
        }

        // create a default account
        AccountHandler::createDefaultAccount($address);

        return $helper->transformResourceForOutput($address);
    }

}
