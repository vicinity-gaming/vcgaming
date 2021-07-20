<?php
/**
 * @brief            Vicinity Gaming Application Class
 * @author           <a href='https://vicinitygaming.com'>Vicinity Gaming</a>
 * @copyright    (c) 2021 Vicinity Gaming
 * @package          Invision Community
 * @subpackage       Vicinity Gaming
 * @since            11 Jul 2021
 * @version
 */

namespace IPS\vcgaming;

/**
 * Vicinity Gaming Application Class
 */
class _Application extends \IPS\Application
{
    /**
     * Get the Discord DB connection used throughout the application.
     *
     * @return \IPS\Db
     * @throws \Exception
     */
    public static function getDiscordDb() : \IPS\Db
    {
        if (\IPS\Settings::i()->vcg_discord_db_setup)
        {
            return \IPS\Db::i(
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
        }
        else
        {
            throw new \Exception('Discord DB not set up.');
        }
    }
}