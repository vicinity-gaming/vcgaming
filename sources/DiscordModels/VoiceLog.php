<?php

namespace IPS\vcgaming\DiscordModels;

/**
 * Class VoiceLog
 *
 * Represents the moment in time a member joins or leaves a certain channel allowing for tracking of voice
 * activity and subsequent XP issue.
 *
 * @author Carlos Amores
 *
 * @property int       $id               Row UID.
 * @property string    $discord_id       The Discord ID of the member who owns the voice log.
 * @property string    $guild_id         The ID of the guild for which the activity was recorded.
 * @property string    $channel_id       The ID of the channel which was joined or left.
 * @property int       $vc_action        The action which occurred at the specified time.
 * @property int       $log_type         The type of log which determines the XP rate to be used when issuing the XP.
 * @property \DateTime $action_timestamp The moment in time when the action took place.
 * @property bool      $processed        Whether XP has already been issued for the given voice log.
 */
class _VoiceLog extends \IPS\Patterns\ActiveRecord
{
    /**
     * Possible values of $vc_action
     */
    public const ACTION_JOIN  = 1 << 0;
    public const ACTION_LEAVE = 1 << 1;
    /**
     * Possible values of $log_type
     */
    public const TYPE_STANDARD = 1 << 0;
    public const TYPE_EVENT    = 1 << 1;
    /**
     * [ActiveRecord] Database table
     * @var string
     */
    public static $databaseTable = 'voice_logs';
    /**
     * [ActiveRecord] ID Database Column
     * @var string
     */
    public static $databaseColumnId = 'id';
    /**
     * [ActiveRecord] Multiton Store
     * @var array
     */
    protected static $multitons = [];

    /**
     * [ActiveRecord] Database Connection
     *
     * @return \IPS\Db
     * @throws \Exception
     */
    public static function db()
    {
        return \IPS\vcgaming\Application::getDiscordDb();
    }

    /**
     * action_timestamp getter.
     * @return \DateTime
     * @throws \Exception
     */
    public function get_action_timestamp() : \DateTime
    {
        return new \DateTime($this->_data['action_timestamp'], new \DateTimeZone('UTC'));
    }

    /**
     * action_timestamp setter.
     * @param \DateTime $date
     */
    public function set_action_timestamp(\DateTime $date) : void
    {
        $this->_data['action_timestamp'] = $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
