<?php

/*
 * Copyright (C) 2009-2013 Internet Neutral Exchange Association Limited.
 * All Rights Reserved.
 *
 * This file is part of IXP Manager.
 *
 * IXP Manager is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, version v2.0 of the License.
 *
 * IXP Manager is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License v2.0
 * along with IXP Manager.  If not, see:
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */


/**
 * Controller: Router CLI Actions (such as collectors and servers)
 *
 * @author     Barry O'Donovan <barry@opensolutions.ie>
 * @category   IXP
 * @package    IXP_Controller
 * @copyright  Copyright (c) 2009 - 2013, Internet Neutral Exchange Association Ltd
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU GPL V2.0
 */
class RouterCliController extends IXP_Controller_CliAction
{
    /**
     * Action to generate a route collector configuration
     *
     * @see https://github.com/inex/IXP-Manager/wiki/Route-Collector
     */
    public function genCollectorConfAction()
    {
        $this->view->vlan = $vlan = $this->cliResolveVlanId();

        $target = $this->cliResolveTarget(
            isset( $this->_options['router']['collector']['conf']['target'] )
                ? $this->_options['router']['collector']['conf']['target']
                : false
        );

        $this->view->asn = $this->cliResolveASN(
                isset( $this->_options['router']['collector']['conf']['asn'] )
                ? $this->_options['router']['collector']['conf']['asn']
                : false
        );

        $this->collectorConfSanityCheck( $vlan );

        $this->view->proto = $proto = $this->cliResolveProtocol( false );

        if( !$proto || $proto == 4 )
            $this->view->v4ints = $this->sanitiseVlanInterfaces( $vlan, 4 );

        if( !$proto || $proto == 6 )
            $this->view->v6ints = $this->sanitiseVlanInterfaces( $vlan, 6 );

        if( isset( $this->_options['router']['collector']['conf']['dstpath'] ) )
        {
            if( !$this->writeConfig( $this->_options['router']['collector']['conf']['dstpath'] . "/rc-{$vlan->getId()}.conf",
                    $this->view->render( "router-cli/collector/{$target}/index.cfg" ) ) )
            {
                fwrite( STDERR, "Error: could not save configuration data\n" );
            }
        }
        else
            echo $this->view->render( "router-cli/collector/{$target}/index.cfg" );
    }

    
    /**
     * Action to generate a route server configuration
     *
     * @see https://github.com/inex/IXP-Manager/wiki/Route-Server
     */
    public function genServerConfAction()
    {
        $this->view->vlan = $vlan = $this->cliResolveVlanId();
    
        $target = $this->cliResolveTarget(
                isset( $this->_options['router']['collector']['conf']['target'] )
                ? $this->_options['router']['collector']['conf']['target']
                : false
        );
    
        $this->view->proto = $proto = $this->cliResolveProtocol( false );
    
        if( $proto == 6 )
            $ints = $this->sanitiseVlanInterfaces( $vlan, 6, true );
        else
        {
            $ints = $this->sanitiseVlanInterfaces( $vlan, 4, true );
            $this->view->proto = $proto = 4;
        }
        
        // should we limit this to one customer only?
        $lcustomer = $this->cliResolveParam( 'cust', false, false );
        
        // should we wrap the output with the header and footer
        $wrappers = (bool)$this->cliResolveParam( 'wrappers', false, true );

        // is test mode enabled?
        $this->view->testmode = (bool)$this->cliResolveParam( 'testmode', false, false );

        // load Smary config file
        $this->getView()->configLoad( $this->loadConfig() );
        
        if( !$lcustomer && $wrappers && $this->getView()->templateExists( "router-cli/server/{$target}/header.cfg" ) )
            echo $this->view->render( "router-cli/server/{$target}/header.cfg" );
        
        foreach( $ints as $int )
        {
            if( $lcustomer && $int['cshortname'] != $lcustomer )
                continue;
            
            // $this->view->cust = $this->getD2R( '\\Entities\\Customer' )->find( $int[ 'cid' ] );
            $this->view->int  = $int;
            $this->view->prefixes = $this->getD2R( '\\Entities\\IrrdbPrefix' )->getForCustomerAndProtocol( $int[ 'cid' ], $proto );
            echo $this->view->render( "router-cli/server/{$target}/neighbor.cfg" );
        }
        
        if( !$lcustomer && $wrappers && $this->getView()->templateExists( "router-cli/server/{$target}/footer.cfg" ) )
            echo $this->view->render( "router-cli/server/{$target}/footer.cfg" );
    }
    
    /**
     * Action to generate test route server client configurations
     *
     * @see https://github.com/inex/IXP-Manager/wiki/Route-Server-Testing
     */
    public function genServerTestConfsAction()
    {
        $this->view->vlan = $vlan = $this->cliResolveVlanId();
    
        $target = $this->cliResolveTarget(
                isset( $this->_options['router']['collector']['conf']['target'] )
                ? $this->_options['router']['collector']['conf']['target']
                : false
        );
    
        $this->view->proto = $proto = $this->cliResolveProtocol( false );
    
        if( $proto == 6 )
            $ints = $this->sanitiseVlanInterfaces( $vlan, 6, true );
        else
        {
            $ints = $this->sanitiseVlanInterfaces( $vlan, 4, true );
            $this->view->proto = $proto = 4;
        }

        // prepare the test directory and its subdirectories
        $dir = $this->prepareTestDirectory();
        
        // should we limit this to one customer only?
        $lcustomer = $this->cliResolveParam( 'cust', false, false );
    
        // load Smary config file
        $this->getView()->configLoad( $this->loadConfig() );
    
        foreach( $ints as $int )
        {
            if( $lcustomer && $int['cshortname'] != $lcustomer )
                continue;
    
            $this->view->int  = $int;
            $this->view->prefixes = $this->getD2R( '\\Entities\\IrrdbPrefix' )->getForCustomerAndProtocol( $int[ 'cid' ], $proto );
            file_put_contents( "{$dir}/confs/{$int['cshortname']}-vlanid{$vlan->getId()}-ipv{$proto}.conf", $this->view->render( "router-cli/server-testing/{$target}.cfg" ) );
        }
    }

    /**
     * Action to generate test route server client setup commands
     *
     * @see https://github.com/inex/IXP-Manager/wiki/Route-Server-Testing
     */
    public function genServerTestSetupAction()
    {
        $this->view->vlan = $vlan = $this->cliResolveVlanId();
    
        $target = $this->cliResolveTarget(
                isset( $this->_options['router']['collector']['conf']['target'] )
                ? $this->_options['router']['collector']['conf']['target']
                : false
        );
    
        $this->view->proto = $proto = $this->cliResolveProtocol( false );
    
        if( $proto == 6 )
            $ints = $this->sanitiseVlanInterfaces( $vlan, 6, true );
        else
        {
            $ints = $this->sanitiseVlanInterfaces( $vlan, 4, true );
            $this->view->proto = $proto = 4;
        }

        if( $vlan->getSubnetSize( $proto ) === false )
            throw new IXP_Exception( "Subnet size for this VLAN is not defined. See http://git.io/TkSVVw" );
        
        // should we limit this to one customer only?
        $lcustomer = $this->cliResolveParam( 'cust', false, false );
    
        // prepare the test directory and its subdirectories
        $this->view->dir = $this->prepareTestDirectory();
        
        // the OS to generate commands for
        $os = $this->cliResolveParam( 'os', false, 'linux' );
        
        // generate down rather than up commands?
        $down = $this->cliResolveParam( 'down', false, false );
        
        // load Smary config file
        $this->getView()->configLoad( $this->loadConfig() );
    
        foreach( $ints as $int )
        {
            if( $lcustomer && $int['cshortname'] != $lcustomer )
                continue;
    
            $this->view->int  = $int;
            if( $down )
                echo $this->view->render( "router-cli/server-testing/{$target}-{$os}-setup-down.cfg" );
            else
                echo $this->view->render( "router-cli/server-testing/{$target}-{$os}-setup-up.cfg" );
        }
    }
    
    /**
     * Used by the route server test generator to create and prepare a test
     * directory.
     *
     * @see genServerTestConfsAction()
     */
    private function prepareTestDirectory()
    {
        // we need a directory to spit out the files
        $dir = realpath( $this->cliResolveParam( 'dir', true ) );

        if( !$dir )
            throw new IXP_Exception( "The target directory does not exist" );
        else if( !is_dir( $dir ) )
            throw new IXP_Exception( "{$dir} exists but is not a directory" );
        else if( !is_writable( $dir ) )
            throw new IXP_Exception( "{$dir} is not writable" );
        
        if( !file_exists( "{$dir}/confs" ) )
        {
            if( !mkdir( "{$dir}/confs" ) )
            {
                echo "ERROR: {$dir}/confs does not exist and could not be created\n";
                return;
            }
        }
        
        return $dir;
    }
    
    /**
     * Action to generate an AS112 router configuration
     *
     * @see https://github.com/inex/IXP-Manager/wiki/AS112
     */
    public function genAs112ConfAction()
    {
        $this->view->vlan = $vlan = $this->cliResolveVlanId();

        $target = $this->cliResolveTarget(
            isset( $this->_options['router']['as112']['conf']['target'] )
                ? $this->_options['router']['as112']['conf']['target']
                : false
        );

        if( $this->getParam( 'rc', false ) )
        {
            $this->view->routeCollectors   = $vlan->getRouteCollectors( \Entities\Vlan::PROTOCOL_IPv4 );
            $this->view->routeCollectorASN = $this->getParam( 'rcasn', 65500 );
        }

        $this->view->v4ints = $this->sanitiseVlanInterfaces( $vlan, 4 );

        if( isset( $this->_options['router']['as112']['conf']['dstpath'] ) )
        {
            if( !$this->writeConfig( $this->_options['router']['as112']['conf']['dstpath'] . "/as112-{$vlan->getId()}.conf",
                    $this->view->render( "router-cli/as112/{$target}/index.cfg" ) ) )
            {
                fwrite( STDERR, "Error: could not save configuration data\n" );
            }
        }
        else
            echo $this->view->render( "router-cli/as112/{$target}/index.cfg" );
    }

    /**
     * Action to generate a TACACS+ configuration
     *
     * @see https://github.com/inex/IXP-Manager/wiki/TACACS
     */
    public function genTacacsConfAction()
    {
        $this->view->users = $this->getD2R( '\\Entities\\User' )->arrangeByType();
    
        $dstfile                    = $this->cliResolveParam( 'dstfile',        false );
        $target                     = $this->cliResolveParam( 'target',         true, 'tacplus' );
        $this->view->secret         = $this->cliResolveParam( 'secret',         true, 'soopersecret' );
        $this->view->accountingfile = $this->cliResolveParam( 'accountingfile', true, '/var/log/tac_plus/tac_plus.log' );
        
        if( $dstfile )
        {
            if( !$this->writeConfig( $dstfile, $this->view->render( "router-cli/tacacs/{$target}/index.cfg" ) ) )
                fwrite( STDERR, "Error: could not save configuration data\n" );
        }
        else
            echo $this->view->render( "router-cli/tacacs/{$target}/index.cfg" );
    }
    
    /**
     * This is a summy function for gen-tacacs-conf.
     *
     * @see https://github.com/inex/IXP-Manager/wiki/RADIUS
     */
    public function genRadiusConfAction()
    {
        $this->forward( 'gen-tacacs-conf' );
    }
    
    /**
     * The collector configuration expects some data to be available. This function
     * gathers and checks that data.
     *
     */
    private function collectorConfSanityCheck( $vlan )
    {
        /*
        // get the available reoute collectors and set the IP of the first as
        // the route collector router ID.
        $collectors = $vlan->getRouteCollectors( \Entities\Vlan::PROTOCOL_IPv4 );

        if( !is_array( $collectors ) || !count( $collectors ) )
        {
            die(
                "ERROR: Not IPv4 route collectors defined in the VLANs network information table\n"
                    . "    See: https://github.com/inex/IXP-Manager/wiki/Network-Information\n"
            );
        }

        $this->view->routerId = $collectors[0];
        */

        /*
        if( !isset( $this->_options['router']['collector']['conf']['asn'] ) )
            die( "ERROR: No route collector ASN configured in application.ini\n");
        */
    }

    /**
     * Utility function to get and return active VLAN interfaces on the requested protocol
     * suitable for route collector / server configuration.
     *
     * Sample return:
     *
     *     [
     *         [cid] => 999
     *         [cname] => Customer Name
     *         [cshortname] => shortname
     *         [autsys] => 65000
     *         [peeringmacro] => QWE              // or AS65500 if not defined
     *         [vliid] => 159
     *         [address] => 192.0.2.123
     *         [bgpmd5secret] => qwertyui         // or false
     *         [as112client] => 1                 // if the member is an as112 client or not
     *         [rsclient] => 1                    // if the member is a route server client or not
     *         [maxprefixes] => 20
     *         [irrdbfilter] => 0/1               // if IRRDB filtering should be applied
     *         [location_name] => Interxion DUB1
     *         [location_shortname] => IX-DUB1
     *         [location_tag] => ix1
     *     ]
     *
     * @param \Entities\Vlan $vlan
     * @param int $proto
     * @return array As defined above
     */
    private function sanitiseVlanInterfaces( $vlan, $proto, $rsclient = false )
    {
        $ints = $this->getD2R( '\\Entities\\VlanInterface' )->getForProto( $vlan, $proto, false );
        
        $newints = [];

        foreach( $ints as $int )
        {
            if( !$int['enabled'] )
                continue;

            if( $rsclient && !$int['rsclient'] )
                continue;
            
            // Due the the way we format the SQL query to join with physical
            // interfaces (of which there may be multiple per VLAN interface),
            // we need to weed out duplicates
            if( isset( $newints[ $int['address'] ] ) )
                continue;

            unset( $int['enabled'] );

            if( $int['maxbgpprefix'] && $int['maxbgpprefix'] > $int['gmaxprefixes'] )
                $int['maxprefixes'] = $int['maxbgpprefix'];
            else
                $int['maxprefixes'] = $int['gmaxprefixes'];

            if( !$int['maxprefixes'] )
                $int['maxprefixes'] = 20;

            unset( $int['gmaxprefixes'] );
            unset( $int['maxbgpprefix'] );

            if( $proto == 6 && $int['peeringmacrov6'] )
                $int['peeringmacro'] = $int['peeringmacrov6'];

            if( !$int['peeringmacro'] )
                $int['peeringmacro'] = 'AS' . $int['autsys'];

            unset( $int['peeringmacrov6'] );

            if( !$int['bgpmd5secret'] )
                $int['bgpmd5secret'] = false;

            $newints[ $int['address'] ] = $int;
        }

        return $newints;
    }
}

