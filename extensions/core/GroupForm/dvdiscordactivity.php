<?php
/**
 * @brief            Admin CP Group Form
 * @author           <a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright    (c) Invision Power Services, Inc.
 * @license          https://www.invisioncommunity.com/legal/standards/
 * @package          Invision Community
 * @subpackage       Vicinity Gaming
 * @since            13 Jul 2021
 */

namespace IPS\vcgaming\extensions\core\GroupForm;

/* To prevent PHP errors (extending class does not exist) revealing path */
if (!\defined('\IPS\SUITE_UNIQUE_KEY'))
{
    header((isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0') . ' 403 Forbidden');
    exit;
}

/**
 * Admin CP Group Form
 */
class _dvdiscordactivity
{
    /**
     * Process Form
     *
     * @param \IPS\Helpers\Form $form  The form
     * @param \IPS\Member\Group $group Existing Group
     * @return    void
     */
    public function process(&$form, $group)
    {
        if ($group->g_id !== \IPS\Settings::i()->guest_group)
        {
            $viewDiscordActivityField = new \IPS\Helpers\Form\YesNo(
                'g_viewdiscordactivity',
                $group->g_viewdiscordactivity
            );
            $form->add($viewDiscordActivityField);
        }
    }

    /**
     * Save
     *
     * @param array             $values Values from form
     * @param \IPS\Member\Group $group  The group
     * @return    void
     */
    public function save($values, &$group)
    {
        $group->g_viewdiscordactivity = $values['g_viewdiscordactivity'];
    }
}
