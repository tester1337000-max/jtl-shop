<?php

declare(strict_types=1);

namespace JTL\Mail\Template;

use JTL\Customer\Customer;
use JTL\Smarty\JTLSmarty;

/**
 * Class Checkbox
 * @package JTL\Mail\Template
 */
class Checkbox extends AbstractTemplate
{
    protected ?string $id = \MAILTEMPLATE_CHECKBOX_SHOPBETREIBER;

    /**
     * @inheritdoc
     */
    public function preRender(JTLSmarty $smarty, mixed $data): void
    {
        parent::preRender($smarty, $data);
        /** @var \stdClass|null $data */
        if ($data === null) {
            return;
        }
        /** @var Customer|\stdClass $customer */
        $customer = $data->oKunde;
        $smarty->assign('oCheckBox', $data->oCheckBox)
            ->assign('oKunde', $customer)
            ->assign('cAnzeigeOrt', $data->cAnzeigeOrt)
            ->assign('oSprache', (object)['kSprache' => $this->languageID]);
        $subjectLineCustomer = empty($customer->cVorname) && empty($customer->cNachname)
            ? $customer->cMail
            : $customer->cVorname . ' ' . $customer->cNachname;
        $this->setSubject($data->oCheckBox->cName . ' - ' . $subjectLineCustomer);
    }
}
