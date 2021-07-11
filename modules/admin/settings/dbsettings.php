<?php


namespace IPS\vcgaming\modules\admin\settings;

/* To prevent PHP errors (extending class does not exist) revealing path */

if (!\defined('\IPS\SUITE_UNIQUE_KEY'))
{
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * dbsettings
 */
class _dbsettings extends \IPS\Dispatcher\Controller
{
    /**
     * Execute
     *
     * @return    void
     */
    public function execute()
    {
        \IPS\Dispatcher::i()->checkAcpPermission('dbsettings_manage');
        parent::execute();
    }

    /**
     * This is the default method if no 'do' parameter is specified
     *
     * @return    void
     */
    protected function manage()
    {
        \IPS\Output::i()->title .= \IPS\Member::loggedIn()->language()->addToStack('menu__vcgaming_settings_dbsettings');
        $settingsForm           = new \IPS\Helpers\Form();
        $discordDbField         = new \IPS\Helpers\Form\Text(
            'vcg_discord_mysql_db',
            \IPS\Settings::i()->vcg_discord_mysql_db,
            true,
            [
                'bypassProfanity' => \IPS\Helpers\Form\Text::BYPASS_PROFANITY_ALL,
            ]
        );
        $discordHostField       = new \IPS\Helpers\Form\Text(
            'vcg_discord_mysql_host',
            \IPS\Settings::i()->vcg_discord_mysql_host,
            true,
            [
                'bypassProfanity' => \IPS\Helpers\Form\Text::BYPASS_PROFANITY_ALL,
            ]
        );
        $discordPassField       = new \IPS\Helpers\Form\Password(
            'vcg_discord_mysql_pass',
            null,
            true,
            [
                'bypassProfanity' => \IPS\Helpers\Form\Text::BYPASS_PROFANITY_ALL,
            ]
        );
        $discordPortField       = new \IPS\Helpers\Form\Number(
            'vcg_discord_mysql_port',
            \IPS\Settings::i()->vcg_discord_mysql_port,
            true
        );
        $discordUserField       = new \IPS\Helpers\Form\Text(
            'vcg_discord_mysql_user',
            \IPS\Settings::i()->vcg_discord_mysql_user,
            true,
            [
                'bypassProfanity' => \IPS\Helpers\Form\Text::BYPASS_PROFANITY_ALL,
            ]
        );

        $settingsForm->add($discordHostField);
        $settingsForm->add($discordDbField);
        $settingsForm->add($discordUserField);
        $settingsForm->add($discordPassField);
        $settingsForm->add($discordPortField);

        \IPS\Output::i()->output .= $settingsForm;

        if ($values = $settingsForm->values())
        {
            // Verify database connectivity.
            $discordDb = \IPS\Db::i(
                'vcg_discord_db',
                [
                    'sql_host'     => $values['vcg_discord_mysql_host'],
                    'sql_user'     => $values['vcg_discord_mysql_user'],
                    'sql_pass'     => $values['vcg_discord_mysql_pass'],
                    'sql_database' => $values['vcg_discord_mysql_db'],
                    'sql_port'     => $values['vcg_discord_mysql_port'],
                    'sql_utf8mb4'  => true,
                ]
            );

            try
            {
                $discordDb->checkConnection();
            }
            catch (\IPS\Db\Exception $e)
            {
                \IPS\Output::i()->error('vcg_failed_db_conn', '1V100/1', 400);
            }

            $settingsForm->saveAsSettings();
            \IPS\Settings::i()->vcg_discord_db_setup = true;
            \IPS\Output::i()->redirect($this->url, 'vcg_dbsettings_success');
        }
    }
}
