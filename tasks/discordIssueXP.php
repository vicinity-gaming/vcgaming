<?php
/**
 * @brief            discordIssueXP Task
 * @author           <a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright    (c) Invision Power Services, Inc.
 * @license          https://www.invisioncommunity.com/legal/standards/
 * @package          Invision Community
 * @subpackage       vcgaming
 * @since            11 Jul 2021
 */

namespace IPS\vcgaming\tasks;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY'))
{
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * discordIssueXP Task
 */
class _discordIssueXP extends \IPS\Task
{
    /**
     * Execute
     *
     * If ran successfully, should return anything worth logging. Only log something
     * worth mentioning (don't log "task ran successfully"). Return NULL (actual NULL, not '' or 0) to not log (which
     * will be most cases). If an error occurs which means the task could not finish running, throw an
     * \IPS\Task\Exception - do not log an error as a normal log. Tasks should execute within the time of a normal HTTP
     * request.
     *
     * @return    mixed    Message to log or NULL
     * @throws    \IPS\Task\Exception
     */
    public function execute()
    {
        if (!\IPS\Settings::i()->vcg_discord_db_setup || !\IPS\Settings::i()->vcg_xp_setup)
        {
            throw new \IPS\Task\Exception($this, 'vcg_discord_xp_missing_setup');
        }

        // Detect whether Brilliant Discord integration is installed.
        try
        {
            \IPS\Application::load('brilliantdiscord', null, ['`app_enabled`=?', true]);
        }
        catch (\OutOfRangeException $e)
        {
            throw new \IPS\Task\Exception($this, 'vcg_brilliant_discord_not_installed');
        }

        // Detect the login method ID for BD.
        $loginMethodId = \IPS\Db::i()->select(
            'login_id',
            'core_login_methods',
            ['`login_classname`=?', 'IPS\\brilliantdiscord\\LoginHandler']
        )->first();

        // Connect to the Discord database prior to queueing as to only perform the connection once per task execution.
        $discordDb    = \IPS\Db::i(
            'vcg_discord_db',
            [
                'sql_host'     => \IPS\Settings::i()->vcg_discord_mysql_host,
                'sql_user'     => \IPS\Settings::i()->vcg_discord_mysql_user,
                'sql_pass'     => \IPS\Settings::i()->vcg_discord_mysql_pass,
                'sql_database' => \IPS\Settings::i()->vcg_discord_mysql_db,
                'sql_port'     => \IPS\Settings::i()->vcg_discord_mysql_port,
                'sql_utf8mb4'  => true,
            ]
        );
        $voiceLogIter = new \IPS\Patterns\ActiveRecordIterator(
            $discordDb->select(
                '*',
                \IPS\Application\vcgaming\DiscordModels\VoiceLog::$databaseTable,
                ['`processed`=?', false],
                '`action_timestamp`, `vc_action`'
            ),
            \IPS\Application\vcgaming\DiscordModels\VoiceLog::class
        );

        \IPS\Task::queue(
            'vcgaming',
            'discordIssueXP',
            [
                'voiceLogs'     => \iterator_to_array($voiceLogIter),
                'loginMethodId' => $loginMethodId,
            ],
            3
        );
        return null;
    }

    /**
     * Cleanup
     *
     * If your task takes longer than 15 minutes to run, this method
     * will be called before execute(). Use it to clean up anything which
     * may not have been done
     *
     * @return    void
     */
    public function cleanup()
    {
    }
}