<?php
namespace App\Payment\General;

use App\Payment\DirectBilling\DirectBillingChargeWallet;
use App\Payment\Interfaces\IChargeWallet;
use App\Payment\Sms\SmsChargeWallet;
use App\Payment\Transfer\TransferChargeWallet;
use App\System\Application;
use UnexpectedValueException;

class ChargeWalletFactory
{
    /** @var Application */
    private $app;

    /** @var array */
    private $paymentMethodsClasses;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->paymentMethodsClasses = [
            PaymentMethod::DIRECT_BILLING()->getValue() => DirectBillingChargeWallet::class,
            PaymentMethod::SMS()->getValue() => SmsChargeWallet::class,
            PaymentMethod::TRANSFER()->getValue() => TransferChargeWallet::class,
        ];
    }

    /**
     * @return IChargeWallet[]
     */
    public function createAll()
    {
        return collect($this->paymentMethodsClasses)
            ->map(function ($class) {
                return $this->app->make($class);
            })
            ->all();
    }

    /**
     * @param PaymentMethod $paymentMethod
     * @return IChargeWallet
     * @throws UnexpectedValueException
     */
    public function create(PaymentMethod $paymentMethod)
    {
        if (isset($this->paymentMethodsClasses[$paymentMethod->getValue()])) {
            return $this->app->make($this->paymentMethodsClasses[$paymentMethod->getValue()]);
        }

        throw new UnexpectedValueException("Payment method [$paymentMethod] doesn't exist");
    }
}
