<?php


namespace IPS\vcgaming\modules\front\utils;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY'))
{
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * xpissuer
 */
class _xpissuer extends \IPS\Dispatcher\Controller
{
    /**
     * Execute
     *
     * @return    void
     */
    public function execute()
    {
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
            if ($group->g_usexpissuer)
            {
                $canView = true;
                break;
            }
        }

        if (!$canView)
        {
            \IPS\Output::i()->error('vcg_front_no_permission', '1V102/1');
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
        \IPS\Output::i()->title .= \IPS\Member::loggedIn()->language()->addToStack('vcg_xi_title');
        $xpIssueForm            = new \IPS\Helpers\Form(
            'form',
            'vcg_xi_submit_lang'
        );
        $memberField            = new \IPS\Helpers\Form\Member(
            'vcg_xi_member',
            null,
            true
        );
        $xpAmountField          = new \IPS\Helpers\Form\Number(
            'vcg_xi_xp_amount',
            null,
            true,
            [
                'min'      => null,
                'decimals' => 0,
            ]
        );
        $reasonField            = new \IPS\Helpers\Form\Text(
            'vcg_xi_reason',
            null,
            true,
            [
                'minLength' => 1,
            ]
        );

        $xpIssueForm->add($memberField);
        $xpIssueForm->add($xpAmountField);
        $xpIssueForm->add($reasonField);

        \IPS\Output::i()->output .= $xpIssueForm;

        if ($values = $xpIssueForm->values())
        {
            /** @var \IPS\Member $member */
            $member = $values['vcg_xi_member'];

            $log              = new \IPS\vcgaming\ForumModels\XpLog();
            $log->member_id   = $member->member_id;
            $log->operator_id = \IPS\Member::loggedIn()->member_id;
            $log->xp_amount   = $values['vcg_xi_xp_amount'];
            $log->previous_xp = $member->pp_reputation_points;
            $log->timestamp   = new \DateTime();
            $log->reason      = $values['vcg_xi_reason'];
            $log->save();

            $member->pp_reputation_points += $values['vcg_xi_xp_amount'];
            $member->save();

            \IPS\Output::i()->redirect($this->url, 'vcg_xi_issued');
        }
    }
}
