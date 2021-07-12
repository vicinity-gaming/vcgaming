<?php
/**
 * @brief            Background Task
 * @author           <a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright    (c) Invision Power Services, Inc.
 * @license          https://www.invisioncommunity.com/legal/standards/
 * @package          Invision Community
 * @subpackage       Vicinity Gaming
 * @since            11 Jul 2021
 */

namespace IPS\vcgaming\extensions\core\Queue;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY'))
{
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * Background Task
 */
class _discordIssueXP
{
    /**
     * Parse data before queuing
     *
     * @param array $data
     * @return    array
     */
    public function preQueueData($data)
    {
        /** @var \IPS\Application\vcgaming\DiscordModels\VoiceLog[] $voiceLogs */
        $voiceLogs      = $data['voiceLogs'];
        $memberActivity = [];
        $returnData     = [
            'memberXp'  => [],
            'processed' => [],
        ];

        // Sort the voice logs in a container such that they are broken down by discord ID.
        foreach ($voiceLogs as $voiceLog)
        {
            if (!isset($memberActivity[$voiceLog->discord_id]))
            {
                $memberActivity[$voiceLog->discord_id] = [];
            }

            $memberActivity[$voiceLog->discord_id][] = $voiceLog;
        }

        // Map the Discord IDs to the forum IDs.
        $forumIdMap = \IPS\Db::i()->select(
            [
                'token_member',
                'token_identifier',
            ],
            'core_login_links',
            [
                ['`token_login_method=?`', $data['loginMethodId']],
                \IPS\Db::i()->in('token_identifier', \array_keys($memberActivity)),
            ]
        );
        $forumIdMap->setKeyField('token_identifier');
        $forumIdMap->setValueField('token_member');
        // Convert the \IPS\Db\Select object to an array so it can be accessed by its keys like an array.
        $forumIdMap = \iterator_to_array($forumIdMap);

        // Keep elements which have intersecting keys to discard members without a forum account.
        $memberActivity = \array_intersect_key($memberActivity, $forumIdMap);

        /*
         * Remove the first log in the sorted array if it is a leave log and the last log if it is a join log as they
         * cannot be paired.
         */
        foreach ($memberActivity as $discordId => $memberLogs)
        {
            /*
             * Since the member logs array is going to be iterated using a for loop so we can skip several indices
             * at once (due to taking values in pairs), it cannot have missing keys. Thus, we must index the array again
             * if an element is unset; for that we keep track of it with a flag.
             */
            $reindex = false;

            /** @var \IPS\Application\vcgaming\DiscordModels\VoiceLog[] $memberLogs */
            if ($memberLogs[0]->vc_action === \IPS\Application\vcgaming\DiscordModels\VoiceLog::ACTION_LEAVE)
            {
                unset($memberLogs[0]);
                $reindex = true;
            }
            $memberLogsSize = \count($memberLogs);
            if ($memberLogs[$memberLogsSize]->vc_action === \IPS\Application\vcgaming\DiscordModels\VoiceLog::ACTION_JOIN)
            {
                unset($memberLogs[$memberLogsSize]);
                $reindex = true;
            }

            // If the removal of some log(s) results in an empty array for the given member, remove it as well.
            if (\count($memberLogs) === 0)
            {
                unset($memberActivity[$discordId]);
                continue;
            }

            // Reindex the array now that we are certain it has values and it has been marked for reindexing.
            if ($reindex)
            {
                $memberLogs                 = \array_values($memberLogs);
                $memberActivity[$discordId] = $memberLogs;
            }

            $timeToCredit = 0;
            // Calculate the amount of time the member has spent active in Discord.
            for ($i = 0, $memberLogsSize = \count($memberLogs); $i < $memberLogsSize; $i += 2)
            {
                $timeToCredit              += $memberLogs[$i + 1]->action_timestamp->getTimestamp() - $memberLogs[$i]->action_timestamp->getTimestamp();
                $returnData['processed'][] = $memberLogs[$i]->id;
                $returnData['processed'][] = $memberLogs[$i + 1]->id;
            }

            /*
             * Sadly this weird syntax is used in place of setting the member ID as the key and the time to credit as
             * the value so that later on the progress may be calculated for display in ACP by comparing the current
             * offset to the count of elements in the array.
             */
            $returnData['memberXp'][] = [
                'id'   => $forumIdMap[$discordId],
                'time' => $timeToCredit,
            ];
        }

        return $returnData;
    }

    /**
     * Run Background Task
     *
     * @param mixed $data   Data as it was passed to \IPS\Task::queue()
     * @param int   $offset Offset
     * @return    int                            New offset
     * @throws    \IPS\Task\Queue\OutOfRangeException    Indicates offset doesn't exist and thus task is complete
     */
    public function run($data, $offset)
    {
        $maxIterations = 100;

        $memberActivityData = $data['memberXp'];
        $iterLimit          = $offset + $maxIterations;
        /*
         * Load into memory all the member objects we are going to work with by manually doing the work of
         * IPS\Patterns\ActiveRecordIterator.
         */
        $memberIds    = \array_column(\array_slice($memberActivityData, $offset, $maxIterations), 'id');
        $memberSelect = \IPS\Db::i()->select(
            '*',
            'core_members',
            \IPS\Db::i()->in('member_id', $memberIds)
        );
        foreach ($memberSelect as $memberData)
        {
            \IPS\Member::constructFromData($memberData);
        }

        for (; $offset < $iterLimit; ++$offset)
        {
            if (!isset($memberActivityData[$offset]))
            {
                throw new \IPS\Task\Queue\OutOfRangeException();
            }

            $member                       = \IPS\Member::load($memberActivityData[$offset]['id']);
            $member->pp_reputation_points += \round($memberActivityData[$offset]['time'] * \IPS\Settings::i()->vcg_discord_activity_xp_rate);
            $member->save();
        }

        return $iterLimit;
    }

    /**
     * Get Progress
     *
     * @param mixed $data   Data as it was passed to \IPS\Task::queue()
     * @param int   $offset Offset
     * @return    array( 'text' => 'Doing something...', 'complete' => 50 )    Text explaining task and percentage
     *                      complete
     * @throws    \OutOfRangeException    Indicates offset doesn't exist and thus task is complete
     */
    public function getProgress($data, $offset)
    {
        if (!isset($data['memberXp'][$offset]))
        {
            throw new \OutOfRangeException();
        }

        return ['text' => 'vcg_issuing_discord_xp', 'complete' => $offset / \count($data['memberXp'])];
    }

    /**
     * Perform post-completion processing
     *
     * @param array $data      Data returned from preQueueData
     * @param bool  $processed Was anything processed or not? If preQueueData returns NULL, this will be FALSE.
     * @return    void
     */
    public function postComplete($data, $processed = TRUE)
    {
        // Connect to the Discord DB one last time and set all the relevant voice logs to processed.
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
        $discordDb->update(
            \IPS\Application\vcgaming\DiscordModels\VoiceLog::$databaseTable,
            [
                'processed' => true
            ],
            $discordDb->in('id', $data['processed'])
        );
    }
}