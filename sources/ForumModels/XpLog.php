<?php

namespace IPS\vcgaming\ForumModels;

/**
 * Class XpLog
 *
 * Represents a record of an XP operation on a member's account to keep a paper trail of all relevant changes to
 * somebody's XP count.
 *
 * @author Carlos Amores
 *
 * @property int       $id          Row UID.
 * @property int       $member_id   The ID of the member whose XP was changed.
 * @property int       $operator_id The ID of the member who changed the member's XP.
 * @property int       $xp_amount   The amount of XP that was added or subtracted from the member's total XP.
 * @property int       $previous_xp The member's XP count prior to the operation being performed.
 * @property \DateTime $timestamp   The time when the operation was performed.
 * @property string    $reason      The reason the member's XP is being modified.
 */
class _XpLog extends \IPS\Patterns\ActiveRecord
{
    /**
     * [ActiveRecord] Database table
     * @var string
     */
    public static $databaseTable = 'vcgaming_xp_logs';
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
     * timestamp getter.
     * @return \DateTime
     * @throws \Exception
     */
    public function get_timestamp() : \DateTime
    {
        return new \DateTime($this->_data['timestamp'], new \DateTimeZone('UTC'));
    }

    /**
     * timestamp setter.
     * @param \DateTime $date
     */
    public function set_timestamp(\DateTime $date) : void
    {
        $this->_data['timestamp'] = $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
