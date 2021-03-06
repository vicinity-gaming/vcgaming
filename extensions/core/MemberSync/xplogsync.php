<?php
/**
 * @brief            Member Sync
 * @author           <a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright    (c) Invision Power Services, Inc.
 * @license          https://www.invisioncommunity.com/legal/standards/
 * @package          Invision Community
 * @subpackage       Vicinity Gaming
 * @since            20 Jul 2021
 */

namespace IPS\vcgaming\extensions\core\MemberSync;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY'))
{
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * Member Sync
 */
class _xplogsync
{
    /**
     * Member is merged with another member
     *
     * @param \IPS\Member $member  Member being kept
     * @param \IPS\Member $member2 Member being removed
     * @return    void
     */
    public function onMerge($member, $member2)
    {
        \IPS\Db::i()->update(
            \IPS\vcgaming\ForumModels\XpLog::$databaseTable,
            [
                'member_id' => $member->member_id,
            ],
            ['`member_id`=?', $member2->member_id]
        );
        \IPS\Db::i()->update(
            \IPS\vcgaming\ForumModels\XpLog::$databaseTable,
            [
                'operator_id' => $member->member_id,
            ],
            ['`operator_id`=?', $member2->member_id]
        );

        // Try to update Discord logs table.
        try
        {
            \IPS\Application::load('brilliantdiscord', null, ['`app_enabled`=?', true]);
            \IPS\vcgaming\Application::getDiscordDb()->update(
                \IPS\vcgaming\DiscordModels\VoiceLog::$databaseTable,
                [
                    'discord_id' => \IPS\Db::i()->select('token_identifier', 'core_login_links', ['`token_member`=?', $member->member_id])->first(),
                ],
                ['`discord_id`=?', \IPS\Db::i()->select('token_identifier', 'core_login_links', ['`token_member`=?', $member2->member_id])->first()]
            );
        }
        catch (\OutOfRangeException | \Exception $e)
        {
        }
    }

    /**
     * Member is deleted
     *
     * @param    $member    \IPS\Member    The member
     * @return    void
     */
    public function onDelete($member)
    {
        \IPS\Db::i()->delete(
            \IPS\vcgaming\ForumModels\XpLog::$databaseTable,
            ['`member_id`=?', $member->member_id]
        );

        // Try to delete Discord voice logs for the member.
        try
        {
            \IPS\Application::load('brilliantdiscord', null, ['`app_enabled`=?', true]);
            \IPS\vcgaming\Application::getDiscordDb()->delete(
                \IPS\vcgaming\DiscordModels\VoiceLog::$databaseTable,
                ['`discord_id`=?', \IPS\Db::i()->select('token_identifier', 'core_login_links', ['`token_member`=?', $member->member_id])->first()]
            );
        }
        catch (\OutOfRangeException | \Exception $e)
        {
        }
    }
}