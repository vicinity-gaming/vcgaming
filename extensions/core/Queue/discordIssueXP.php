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
        /** @var \IPS\vcgaming\DiscordModels\VoiceLog[] $voiceLogs */
        $voiceLogs      = $data['voiceLogs'];
        $memberActivity = [];
        $returnData     = [
            'memberXp'  => [],
            'processed' => [],
        ];

        /*
         * This is a retarded way to implement event XP (or other kinds of XP rates for that matter) but the reason for
         * this implementation is that when issuing XP in the run() method defined below there is no access to the type
         * of log for which XP is to be issued. The only data available is the amount of time a person has spent in voice.
         *
         * However, here in preQueueData() when the amount of time between two logs is calculated we have got the
         * VoiceLog object to work with and, therefore we have got access to the type of log and associated XP rate.
         *
         * If we calculate the ratio between the events XP rate and the standard XP rate we can multiply the time by
         * the ratio before adding to the total. This accounts for the time always being multiplied by the standard XP
         * rate.
         *
         * As to why this is utterly retarded; this is a convoluted solution and, it relies on the standard XP rate to
         * never be zero otherwise we would be multiplying the time by \INF which when cast to an integer value as it
         * would be when inserted in the database or converted into JSON, it would be equal to zero.
         */
        $eventToStandardRatio = \IPS\Settings::i()->vcg_discord_event_activity_xp_rate / \IPS\Settings::i()->vcg_discord_activity_xp_rate;

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
                ['`token_login_method`=?', $data['loginMethodId']],
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
             * if an element is unset.
             */

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

            // If the removal of some log(s) results in an empty array for the given member, remove it as well.
            if (\count($memberLogs) === 0)
            {
                unset($memberActivity[$discordId]);
                continue;
            }

            $memberActivity[$discordId] = $memberLogs;

            $timeToCredit = 0;
            // Calculate the amount of time the member has spent active in Discord.
            for ($i = 0, $memberLogsSize = \count($memberLogs); $i < $memberLogsSize; $i += 2)
            {
                if (!isset($memberLogs[$i]) || !isset($memberLogs[$i + 1]))
                {
                    break;
                }

                $timeBetweenLogs = $memberLogs[$i + 1]->action_timestamp->getTimestamp() - $memberLogs[$i]->action_timestamp->getTimestamp();
                if ($memberLogs[$i]->log_type === \IPS\vcgaming\DiscordModels\VoiceLog::TYPE_EVENT && $memberLogs[$i + 1]->log_type === \IPS\vcgaming\DiscordModels\VoiceLog::TYPE_EVENT)
                {
                    $timeBetweenLogs *= $eventToStandardRatio;
                }

                $timeToCredit              += $timeBetweenLogs;
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

            $ts       = new \DateTime();
            $member   = \IPS\Member::load($memberActivityData[$offset]['id']);
            $xpAmount = \round($memberActivityData[$offset]['time'] * \IPS\Settings::i()->vcg_discord_activity_xp_rate);

            // Create an XP when issuing XP automatically as well.
            $log              = new \IPS\vcgaming\ForumModels\XpLog();
            $log->member_id   = $member->member_id;
            $log->operator_id = \IPS\Settings::i()->vcg_community_bot_account;
            $log->xp_amount   = $xpAmount;
            $log->previous_xp = $member->pp_reputation_points;
            $log->timestamp   = $ts;
            $log->reason      = 'Automated Discord XP';
            $log->save();

            $member->pp_reputation_points += $xpAmount;
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

        return ['text' => \IPS\Member::loggedIn()->language()->addToStack('vcg_issuing_discord_xp'), 'complete' => $offset / (\count($data['memberXp']) - 1)];
    }

    /**
     * Perform post-completion processing
     *
     * @param array $data      Data about the task as stored in the core_queue database table.
     * @param bool  $processed Was anything processed or not? If preQueueData returns NULL, this will be FALSE.
     * @return    void
     */
    public function postComplete($data, $processed = TRUE)
    {
        // Connect to the Discord DB one last time and set all the relevant voice logs to processed.
        $discordDb = \IPS\vcgaming\Application::getDiscordDb();
        $discordDb->update(
            \IPS\vcgaming\DiscordModels\VoiceLog::$databaseTable,
            [
                'processed' => true,
            ],
            $discordDb->in('id', \json_decode($data['data'], true)['processed'])
        );
    }
}