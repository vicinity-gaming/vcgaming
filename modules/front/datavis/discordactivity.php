<?php


namespace IPS\vcgaming\modules\front\datavis;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY'))
{
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * discordactivity
 */
class _discordactivity extends \IPS\Dispatcher\Controller
{
    /**
     * Execute
     *
     * @return    void
     */
    public function execute()
    {
        // Check if any of the member's groups allows them to view the page.
        $canView   = false;
        $groupIter = new \IPS\Patterns\ActiveRecordIterator(
            \IPS\Db::i()->select(
                '*',
                \IPS\Member\Group::$databaseTable,
                \IPS\Db::i()->in(\IPS\Member\Group::$databasePrefix . \IPS\Member\Group::$databaseColumnId, \IPS\Member::loggedIn()->groups)
            ),
            \IPS\Member\Group::class
        );
        foreach ($groupIter as $group)
        {
            /** @var \IPS\Member\Group $group */
            if ($group->g_viewdiscordactivity)
            {
                $canView = true;
                break;
            }
        }

        if (!$canView)
        {
            \IPS\Output::i()->error('vcg_front_no_permission', '1V101/1');
        }
        parent::execute();
    }

    /**
     * This is the default method if no 'do' parameter is specified
     *
     * @return    void
     */
    protected function manage()
    {
        if (!\IPS\Settings::i()->vcg_discord_db_setup)
        {
            \IPS\Output::i()->error('vcg_front_discord_db_config', '1V101/2');
        }
        try
        {
            \IPS\Application::load('brilliantdiscord', null, ['`app_enabled`=?', true]);
        }
        catch (\OutOfRangeException $e)
        {
            \IPS\Output::i()->error('vcg_brilliant_discord_not_installed', '1V101/3');
        }

        \IPS\Output::i()->title .= \IPS\Member::loggedIn()->language()->addToStack('vcg_da_monthly');

        $discordDb = \IPS\vcgaming\Application::getDiscordDb();

        $date           = new \DateTime('now', new \DateTimeZone('UTC'));
        $memberActivity = [];
        $voiceLogIter   = new \IPS\Patterns\ActiveRecordIterator(
            $discordDb->select(
                '*',
                'voice_logs',
                ['`action_timestamp` BETWEEN ? AND ?', $date->format('Y-m-01'), $date->format('Y-m-t')],
                '`action_timestamp`, `vc_action`'
            ),
            \IPS\vcgaming\DiscordModels\VoiceLog::class
        );

        foreach ($voiceLogIter as $voiceLog)
        {
            /** @var \IPS\vcgaming\DiscordModels\VoiceLog $voiceLog */
            if (!isset($memberActivity[$voiceLog->discord_id]))
            {
                $memberActivity[$voiceLog->discord_id] = [];
            }

            $memberActivity[$voiceLog->discord_id][] = $voiceLog;
        }

        $loginMethodId = \IPS\Db::i()->select(
            'login_id',
            'core_login_methods',
            ['`login_classname`=?', 'IPS\\brilliantdiscord\\LoginHandler']
        )->first();

        $forumIdMap = \IPS\Db::i()->select(
            [
                'token_member',
                'token_identifier',
            ],
            'core_login_links',
            [
                ['`token_login_method`=?', $loginMethodId],
                \IPS\Db::i()->in('token_identifier', \array_keys($memberActivity)),
            ]
        );
        $forumIdMap->setKeyField('token_identifier');

        $forumIdMap->setValueField('token_member');
        $forumIdMap = \iterator_to_array($forumIdMap);

        $memberActivity = \array_intersect_key($memberActivity, $forumIdMap);

        $tableData = [];
        $forumIds  = [];
        foreach ($memberActivity as $discordId => $memberLogs)
        {
            $forumIds[] = $forumIdMap[$discordId];
            /** @var \IPS\vcgaming\DiscordModels\VoiceLog[] $memberLogs */
            if ($memberLogs[0]->vc_action === \IPS\vcgaming\DiscordModels\VoiceLog::ACTION_LEAVE)
            {
                unset($memberLogs[0]);
                $memberLogs = \array_values($memberLogs);
            }
            $memberLogsSize = \count($memberLogs) - 1;
            if ($memberLogs[$memberLogsSize]->vc_action === \IPS\vcgaming\DiscordModels\VoiceLog::ACTION_JOIN)
            {
                unset($memberLogs[$memberLogsSize]);
                $memberLogs = \array_values($memberLogs);
            }
            $memberActivity[$discordId] = $memberLogs;

            // If the removal of some log(s) results in an empty array for the given member, remove it as well.
            if (\count($memberLogs) === 0)
            {
                unset($memberActivity[$discordId]);
                continue;
            }

            $timeToCredit = 0;
            // Calculate the amount of time the member has spent active in Discord.
            for ($i = 0, $memberLogsSize = \count($memberLogs); $i < $memberLogsSize; $i += 2)
            {
                if (!isset($memberLogs[$i]) || !isset($memberLogs[$i + 1]))
                {
                    break;
                }
                $timeToCredit += $memberLogs[$i + 1]->action_timestamp->getTimestamp() - $memberLogs[$i]->action_timestamp->getTimestamp();
            }
            $tableData[] = [
                'vcg_da_member' => $forumIdMap[$discordId],
                'vcg_da_hours'  => \round($timeToCredit / 3600, 2),
            ];
        }
        // Load all the relevant Member objects in a single DB operation.
        $memberSelect = \IPS\Db::i()->select('*', 'core_members', \IPS\Db::i()->in('member_id', $forumIds));
        foreach ($memberSelect as $memberData)
        {
            \IPS\Member::constructFromData($memberData);
        }

        $table                = new \IPS\Helpers\Table\Custom(
            $tableData,
            $this->url
        );
        $table->tableTemplate = [\IPS\Theme::i()->getTemplate('tables', 'core', 'admin'), 'table'];
        $table->rowsTemplate  = [\IPS\Theme::i()->getTemplate('tables', 'core', 'admin'), 'rows'];
        $table->parsers       = [
            'vcg_da_member' => function ($v)
            {
                $member = \IPS\Member::load($v);
                return \IPS\Theme::i()->getTemplate('global', 'core')->userPhoto($member, 'tiny') . $member->link();
            },
        ];
        $table->sortBy        = \IPS\Request::i()->sortby ?? 'vcg_da_hours';

        \IPS\Output::i()->output .= $table;
    }
}
