<?php


namespace IPS\vcgaming\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY'))
{
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * xpsettings
 */
class _xpsettings extends \IPS\Dispatcher\Controller
{
    /**
     * Execute
     *
     * @return    void
     */
    public function execute()
    {
        \IPS\Dispatcher::i()->checkAcpPermission('xpsettings_manage');
        parent::execute();
    }

    /**
     * This is the default method if no 'do' parameter is specified
     *
     * @return    void
     */
    protected function manage()
    {
        \IPS\Output::i()->title .= \IPS\Member::loggedIn()->language()->addToStack('menu__vcgaming_settings_xpsettings');

        $xpSettingsForm      = new \IPS\Helpers\Form();
        $standardXpRateField = new \IPS\Helpers\Form\Number(
            'vcg_discord_activity_xp_rate',
            \IPS\Settings::i()->vcg_discord_activity_xp_rate,
            true,
            [
                'decimals' => true,
            ]
        );

        $xpSettingsForm->add($standardXpRateField);

        \IPS\Output::i()->output .= $xpSettingsForm;

        if ($values = $xpSettingsForm->values())
        {
            $xpSettingsForm->saveAsSettings();
            \IPS\Settings::i()->changeValues(['vcg_xp_setup' => true]);
            \IPS\Output::i()->redirect($this->url, 'vcg_xpsettings_success');
        }
    }
}
