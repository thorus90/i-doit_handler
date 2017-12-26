<?php
/**
 * i-doit - Documentation and CMDB solution for IT environments
 *
 * This file is part of the i-doit framework. Modify at your own risk.
 *
 * Please visit http://www.i-doit.com/license for a full copyright and license information.
 *
 * @version     1.7.5
 * @package     i-doit
 * @author      synetics GmbH
 * @copyright   synetics GmbH
 * @url         http://www.i-doit.com
 * @license     http://www.i-doit.com/license
 */

/**
 * i-doit
 *
 * This handler exports hosts with static and dhcp reserved ips in bind9 style
 *
 * @package        i-doit
 * @subpackage     Handler
 * @author         Jonas Rottmann <jonas.rottmann@it-novum.com
 * @copyright      it-novum GmbH
 * @version        0.1
 */
class isys_handler_bind9 extends isys_handler
{

    /**
     * @var isys_cmdb_dao
     */
    private $m_dao = null;
    /**
     * Current bind9 types
     *
     * @var array
     */
    private $m_types = [
        'A',
        'PTR'
    ];

    /**
     * Init method
     *
     * @return bool
     */
    public function init()
    {
        verbose("Setting up system environment");

        try
        {
            $this->export_bind9();
        }
        catch (Exception $e)
        {
            verbose($e->getMessage());
        } // try

        return true;
    } // function

    /**
     * Exports bind9 info
     */
    private function export_bind9()
    {
        global $argv, $g_comp_database;;

        // Net object id
        $l_net_obj_id = null;

        // Net address
        $l_net_ip = null;

        // default type
        $l_type = 'A';

        $l_output = '';

        // Help
        if (in_array('-h', $argv))
        {
            $this->usage();
        } // if

        // Layer3 Net ip
        if (in_array('-zone', $argv))
        {
            $l_zone_obj_title = $argv[array_search('-zone', $argv) + 1];
        } // if

        // Layer3 Net address
        if (in_array('-netaddr', $argv))
        {
            $l_net_ip = $argv[array_search('-netaddr', $argv) + 1];
        } // if

        // Type
        if (in_array('-type', $argv))
        {
            $l_type = $argv[array_search('-type', $argv) + 1];
        } // if

        // output usage if type is unknown
        if (!in_array($l_type, $this->m_types))
        {
            verbose("Type unknown please use the default type 'A'.\n");
            $this->usage();
        } // if

        // dao
        $this->m_dao = new isys_cmdb_dao($g_comp_database);

        if (!is_null($l_net_ip))
        {
            $l_net_obj_id = $this->get_net_obj_id_by_net_address($l_net_ip);
        } // if

        switch ($l_type)
        {
            case 'PTR':
                $l_output = $this->host_PTR($l_net_obj_id);
                break;
            case 'A':
                $l_output = $this->zone_A($l_zone_obj_title);
                break;
        } // switch

        echo $l_output;
    } // function

    /**
     * Retrieves object id of the given net address
     *
     * @param $p_net_ip
     *
     * @return null|int
     */
    private function get_net_obj_id_by_net_address($p_net_ip)
    {
        $l_sql = 'SELECT isys_cats_net_list__isys_obj__id FROM isys_cats_net_list ' . 'WHERE isys_cats_net_list__address = ' . $this->m_dao->convert_sql_text($p_net_ip);

        $l_res = $this->m_dao->retrieve($l_sql);
        if ($l_res->num_rows() > 0)
        {
            $l_ip_arr = $l_res->get_row();

            return $l_ip_arr['isys_cats_net_list__isys_obj__id'];
        } // if
        return null;
    } // function

    /** Retrieves DNS Server for net_obj for NS record
     *
     * @param null $p_net_obj_id
     *
     * @return string
     */
    private function get_rDNS_Server($p_net_obj_id = null)
    {
      $l_sql = 'SELECT ';
      $l_sql .= 'isys_cats_net_list__reverse_dns FROM isys_cats_net_list WHERE isys_cats_net_list__isys_obj__id = ' . $this->m_dao->convert_sql_text($p_net_obj_id);
      $l_res = $this->m_dao->retrieve($l_sql);
      if ($l_res->num_rows() > 0)
        {
            $l_r_dns = $l_res->get_row();

            return $l_r_dns['isys_cats_net_list__reverse_dns'];
        } // if
        return null;
    }
    /**
     * Retrieves bind9 PTR blocks
     *
     * @param null $p_net_obj_id
     *
     * @return string
     */
    private function host_PTR($p_net_obj_id = null)
    {
        $l_output = "";

        $l_sql = 'SELECT ';
          $l_sql .= 'ip.isys_cats_net_ip_addresses_list__title AS A, ';
          $l_sql .= 'CONCAT(hostname.isys_catg_ip_list__hostname, \'.\' ,hostname.isys_catg_ip_list__domain) AS FQDN ';

        $l_sql .= 'FROM isys_cats_net_ip_addresses_list AS ip ';

        $l_sql .= 'INNER JOIN isys_catg_ip_list AS hostname ON ip.isys_cats_net_ip_addresses_list__id = hostname.isys_catg_ip_list__isys_cats_net_ip_addresses_list__id ';

        $l_sql .= 'WHERE ip.isys_cats_net_ip_addresses_list__isys_obj__id = ' . $this->m_dao->convert_sql_id($p_net_obj_id) . ' ';
        $l_sql .= 'AND hostname.isys_catg_ip_list__hostname != \'\' ';
        $l_sql .= 'AND hostname.isys_catg_ip_list__isys_net_type__id = 1 ';

        $l_sql .= 'ORDER BY SUBSTRING_INDEX(A,\'.\',-1) * 1';
        $l_res    = $this->m_dao->retrieve($l_sql);
        while ($l_row = $l_res->get_row())
        {
            $arA = explode('.',$l_row['A']);

            $l_output .= end($arA) . "\tIN\tPTR\t" . $l_row['FQDN'] . ".\n";
        }

        return $l_output;
    } // function

    /**
     * Retrieves bind9 A blocks
     *
     * @param null $p_zone_obj_id
     *
     * @return string
     */
    private function zone_A($p_zone_obj_id = null)
    {
        $l_sql = 'SELECT ';
          $l_sql .= 'CONCAT(hostname.isys_catg_ip_list__hostname, \'.\', hostname.isys_catg_ip_list__domain) AS FQDN, ';
          $l_sql .= 'ip.isys_cats_net_ip_addresses_list__title AS A ';

        $l_sql .= 'FROM isys_catg_ip_list AS hostname ';

        $l_sql .= 'INNER JOIN isys_cats_net_ip_addresses_list AS ip ON hostname.isys_catg_ip_list__isys_cats_net_ip_addresses_list__id = ip.isys_cats_net_ip_addresses_list__id ';

        $l_sql .= 'WHERE hostname.isys_catg_ip_list__domain = \'' . $p_zone_obj_id . '\' ';

        $l_sql .= 'ORDER BY CONCAT(hostname.isys_catg_ip_list__hostname, \'.\', hostname.isys_catg_ip_list__domain)';

        $l_res    = $this->m_dao->retrieve($l_sql);
        $l_output = "";

        while ($l_row = $l_res->get_row())
        {
            $arFQDN = explode('.',$l_row['FQDN']);

            $l_output .= $l_row['FQDN'] . ".\t\t\t\tIN\tA\t" . $l_row['A'] . "\n";
        }

        return $l_output;
    } //function

    /**
     * Prints how to use the controller
     */
    private function usage()
    {
        error(
            "Usage: ./controller -m bind9  \n\n" . "Optional Parameter: \n" . "-zone [DNS Zone Name]\n" . "-netaddr [layer3-net-address]\n" . "-type [type]\n\n" . "Current types are:\n" . implode(
                ',',
                $this->m_types
            ) . "\n\n" . "Example: \n" . "./controller -m bind9 -zone example.com -type A\n" . "./controller -m bind9 -netaddr 192.168.10.0 -type PTR\n"
        );
        die;
    } // function
} // class
