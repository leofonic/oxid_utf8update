<?php


require_once "bootstrap.php";

// initializes singleton config class
$myConfig = oxRegistry::getConfig();

/**
 * base updater class, used for providing base utility functions
 * e.g. user interaction
 *
 * @author sarunas
 *
 */
class updateBase extends oxSuperCfg
{

    /**
     * default action, used in start update
     *
     * @var string
     */
    protected $_sDefaultAction = '';

    /**
     * contains class names for update between revisions
     * array keys are rev numbers
     *
     * @var array
     */
    protected static $_aUpdateClasses = array();

    /**
     * revisioned updater entry
     * returns next action
     *
     * @param string $sAction
     *
     * @return string
     */
    public function update($sAction)
    {
        return '';
    }

    /**
     * finds next suitable revision
     *
     * @param int $iCurrRev
     * @return int
     */
    protected static function findNextRevision($iCurrRev)
    {
        if (!isset(self::$_aUpdateClasses[$iCurrRev])) {
            ksort(self::$_aUpdateClasses);
            foreach (self::$_aUpdateClasses as $iRev => $sClass) {
                if ($iRev > $iCurrRev) {
                    return $iRev;
                }
            }
        }

        if (!isset(self::$_aUpdateClasses[$iCurrRev])) {
            return 0;
        }

        return $iCurrRev;
    }


    /**
     * main updater entry
     * @return null
     */
    public static function updateStart()
    {
        $sCurrAction = oxConfig::getParameter( 'sCurrAction' );

        $iCurrRevision = (int)oxConfig::getParameter( 'iCurrRevision' );
        $iCurrRevision = self::findNextRevision($iCurrRevision);

        if ($iCurrRevision == 0) {
            echo "rev not found <br/>\n";
            exit(0);
        }

        $sClass = self::$_aUpdateClasses[$iCurrRevision];

        if (class_exists($sClass)) {
            $oClass = new $sClass;
            if ($oClass instanceof updateBase) {
                if (!$sCurrAction) {
                    $sCurrAction = $oClass->_sDefaultAction;
                }

                $iNextRev = $iCurrRevision;
                $sNextAction = $oClass->update($sCurrAction);
                if (!$sNextAction) {
                    $iNextRev = self::findNextRevision($iCurrRevision + 1);
                }
                $oClass->printUpdateUI($sCurrAction, $sNextAction, $iNextRev);
            } else {
                echo "unknown class found $sClass <br/>\n";
            }
        } else {
            echo "class not found $sClass <br/>\n";
        }
    }

    /**
     * registers new class for update
     *
     * @param int    $iRev
     * @param string $sClass
     * @return null
     */
    public static function registerUpdate($iRev, $sClass)
    {
        self::$_aUpdateClasses[$iRev] = $sClass;
    }

    /**
     * prints update UI
     *
     * @param $sCurrAction
     * @param $sNextAction
     * @param $iRev        'update from' revision, if 0, then done
     *
     * @return null
     */
    protected function printUpdateUI($sCurrAction, $sNextAction, $iRev)
    {
        ?><html>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        body, input { font:11px Trebuchet MS, Tahoma, Verdana, Arial, Helvetica, sans-serif; }
    </style>

    <?php if ( $sNextAction && $iRev ) { ?>
        <meta http-equiv="refresh" content="1;url=<?php echo $this->getConfig()->getShopURL(); ?>update.php?sCurrAction=<?php echo $sNextAction; ?>&iCurrRevision=<?php echo $iRev; ?>">
    <?php } elseif ( $iRev ) { ?>
        <meta http-equiv="refresh" content="1;url=<?php echo $this->getConfig()->getShopURL(); ?>update.php?iCurrRevision=<?php echo $iRev; ?>">
    <?php } ?>

    <title>Update script</title>

    <body>
        <ul>
            <?php if ( !$iRev ) { ?>
                <li>Done</li>
            <?php } else { ?>
                <li>done <?php echo $sCurrAction; ?>,</li>
                <li>going to <?php echo "$sNextAction"; ?>..</li>
            <?php } ?>
        </ul>
    </body>
</html><?php

    }
}

/**
 * update class to utf8
 */
class update_utf8 extends updateBase
{
    /**
     * default action, used in start update
     *
     * @var string
     */
    protected $_sDefaultAction = 'showEncodingSelectionUi';

    /**
     * Amount of records to process per tick
     *
     * @var int
     */
    protected $_iRecPerTick = 50;

    /**
     * Renders GUI for user to select current UI encoding
     *
     * @return null
     */
    public function showEncodingSelectionUi( $blShowCharsetError = false )
    {
        $sCurrCharset = oxLang::getInstance()->translateString( 'charset', null, true );

        ?><html>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        body, input { font:11px Trebuchet MS, Tahoma, Verdana, Arial, Helvetica, sans-serif; }
        body { margin: 20px; }
        b { color: red; }
    </style>
    <title>Update script</title>

    <body>
      <form>
        <input type="hidden" name="sCurrAction" value="setCharset">

        <?php if ( $blShowCharsetError ) { ?>
        <b></>Wrong charset name</b><br>
        <?php } ?>

        Please write old admin charset name:
        <input type="text" name="sCurrCharset" value="<?php echo $sCurrCharset;?>">
        <input type="submit" value="proceed"><br />
        <?php if ( $sCurrCharset ) { echo '(Current value is taken from admin language file)<br>'; } ?>

      </form>
    </body>
    </html><?php
        exit;
    }

    public function setCharset()
    {
        $sCharset = oxConfig::getParameter( 'sCurrCharset' );
        $sCharset = $sCharset ? trim( $sCharset ) : null;

        if ( !$sCharset ) {
            $this->showEncodingSelectionUi();
        }

        // testing if user passed charset name is valid
        if ( iconv( $sCharset, "UTF-8", "test" ) === false ) {
            $this->showEncodingSelectionUi( true );
        }

        oxSession::setVar( 'sCurrCharset', $sCharset );
        return 'backupConfigData';
    }

    /**
     * Config data is very important, so we can only work with backuped
     * data. Returns name of next function to execute
     *
     * @return string
     */
    public function backupConfigData()
    {
        $sQ = "INSERT INTO oxconfig ( oxid, oxshopid, oxvarname, oxvartype, oxvarvalue )
               SELECT CONCAT( '@#', SUBSTRING( MD5( CONCAT( oxid, oxvarname ) ), 2 ) ), oxshopid, oxvarname, oxvartype, oxvarvalue FROM oxconfig WHERE oxid NOT LIKE '@%'";

        oxDb::getDb()->execute( $sQ );

        return 'convertConfigData';
    }

    /**
     * Converts config data. Returns name of next function to execute
     *
     * @return string
     */
    public function convertConfigData()
    {
        $oDb = oxDb::getDb(oxDb::FETCH_MODE_ASSOC);

        // checking if there is still what todo
        $iUpdateCount = $oDb->getOne( "SELECT 1 FROM oxconfig WHERE oxid LIKE '@#%'" );

        // work is done ?
        if ( !$iUpdateCount ) {
            return 'previewConvertedConfigData';
        }

        // continuing update ..
        $sQ  = "SELECT oxid, oxvartype, HEX(DECODE( oxvarvalue, '".$this->getConfig()->getConfigParam( 'sConfigKey' )."')) AS oxvarvalue FROM oxconfig
                WHERE oxid LIKE '@#%'";

        $iUpdateCnt = 0;
        $oRs = oxDb::getDb(oxDb::FETCH_MODE_ASSOC)->Execute( $sQ );
        if ( $oRs != false && $oRs->recordCount() > 0) {
            while ( !$oRs->EOF ) {

                // updating limited count of records per tick ..
                if ( $iUpdateCnt > $this->_iRecPerTick ) {
                    break;
                }

                $sVarId   = $oRs->fields['oxid'];
                $sVarType = $oRs->fields['oxvartype'];
                $sVarVal  = pack( "H*", $oRs->fields['oxvarvalue'] );

                switch ( $sVarType ) {
                    case 'arr':
                    case 'aarr':
                        $this->_encodeConfigArray( $sVarId, $sVarType, $sVarVal );
                        break;
                    case 'str':
                        $this->_encodeConfigString( $sVarId, $sVarType, $sVarVal );
                        break;
                    default:
                        $this->_markAsUpdated( $sVarId, $sVarType );
                }

                $oRs->moveNext();
                $iUpdateCnt++;
            }
        }

        return 'convertConfigData';
    }

    /**
     * Return config instance
     *
     * @return oxconfig
     */
    public function getConfig()
    {
        return oxConfig::getInstance();
    }

    /**
     * Returns user chosen charset
     *
     * @return string
     */
    protected function _getInCharset()
    {
        return oxSession::getVar( 'sCurrCharset' );
        //return "ISO-8859-1";
    }

    /**
     * Returns charset used to encode config data
     *
     * @return string
     */
    protected function _getOutCharset()
    {
        return "UTF-8";
    }

    /**
     * Updates config value
     *
     * @param string $sVarId   variable id
     * @param string $sVarType variable type
     * @param string $sVarVal  variable value
     *
     * @return null
     */
    protected function _updateConfigValue( $sVarId, $sVarType, $sVarVal )
    {
        // updating and marking as updated
        $sQ = "UPDATE oxconfig SET oxvarvalue = ENCODE( '$sVarVal', '".$this->getConfig()->getConfigParam('sConfigKey')."'), oxid = CONCAT( '@', SUBSTRING( oxid, 3 ) ) WHERE oxid = '$sVarId' AND oxvartype = '$sVarType'";
        oxDb::getDb()->execute( $sQ );
    }

    /**
     * Marks config field as updated
     *
     * @return null
     */
    protected function _markAsUpdated( $sVarId, $sVarType )
    {
        // updating and marking as updated
        $sQ = "UPDATE oxconfig SET oxid = CONCAT( '@', SUBSTRING( oxid, 3 ) ) WHERE oxid = '$sVarId' AND oxvartype = '$sVarType'";
        oxDb::getDb()->execute( $sQ );
    }

    /**
           * Returns encoded value
           *
           * @param string $sVal value to encode
           *
           * @return string
           */
    protected function _getEncodedVal( $sVal )
    {
         return iconv( $this->_getInCharset(), $this->_getOutCharset(), $sVal );
    }

    /**
           * Recursivelly encodes config array and returns it
           *
           * @param array $aVarVal config array
           *
           * @return array
           */
    protected function _encodeArray( $aVarVal )
    {
        $aNewVal = array();
        foreach ( $aVarVal as $sArrKey => $sArrValue ) {
            if ( is_array( $sArrValue ) ) {
                $sArrValue = $this->_encodeArray( $sArrValue );
            } else {
                $sArrValue = $this->_getEncodedVal( $sArrValue );
            }
            $aNewVal[ $this->_getEncodedVal( $sArrKey ) ] = $sArrValue;
        }

        return $aNewVal;
    }

    /**
          * ReEncodes array
     *
     * @param string $sVarId   variable id
     * @param string $sVarType variable type
     * @param string $sVarVal  variable value
     *
     * @return null
     */
    protected function _encodeConfigArray( $sVarId, $sVarType, $sVarVal )
    {
        if ( ( $aVarVal = unserialize( $sVarVal ) ) !== false && is_array( $aVarVal ) ) {
            $this->_updateConfigValue( $sVarId, $sVarType, serialize( $this->_encodeArray( $aVarVal ) ) );
        } else {
            $this->_markAsUpdated( $sVarId, $sVarType );
        }

        return $aNewVal;
    }

    /**
     * ReEncodes string value
     *
     * @param string $sVarId   variable id
     * @param string $sVarType variable type
     * @param string $sVarVal  variable value
     *
     * @return null
     */
    protected function _encodeConfigString( $sVarId, $sVarType, $sVarVal )
    {
        $sVarVal = $this->_getEncodedVal( $sVarVal );
        $this->_updateConfigValue( $sVarId, $sVarType, $sVarVal );
    }

    /**
     * Deleting old config data
     *
     * @return null
     */
    public function previewConvertedConfigData()
    {
        // selecting fields which were changed for user preview
        $sQ = "SELECT test1.oxshopid, test1.oxvartype as oxvartype, test1.oxvarname as oxvarname, HEX(DECODE( test1.oxvarvalue, '".$this->getConfig()->getConfigParam( 'sConfigKey' )."')) AS oxvarvalue
              FROM `oxconfig` AS test1, `oxconfig` AS test2
               WHERE test1.oxvarname = test2.oxvarname AND
                     test1.oxshopid = test2.oxshopid AND
                     test1.oxvarvalue != test2.oxvarvalue AND
                     test1.oxid LIKE '@%' ORDER BY test1.oxshopid";

        ?><html>
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        body, input, table { font:11px Trebuchet MS, Tahoma, Verdana, Arial, Helvetica, sans-serif; }
        body { margin: 20px; }
        table td { vertical-align: top; padding: 5px; }
        table td.td0 { background-color: #888888; color: #ffffff; }
    </style>
    <title>Update script</title>

    <body>

      <form>
        <input type="hidden" name="sCurrAction" value="proceedToNextStep">

      <ul>
        <li>
          These config parameters were updated.
          Please review them and, if needed, update manually in admin.<br><br>

          <b>Notice:</b> Press "continue" after you are done<br><br>
          <input type="submit" value="continue"><br><br>
        </li>
      </ul>

      </form>
    <?php

        echo "<table>";

        $sCurrShopId = null;
        $oRs = oxDb::getDb(oxDb::FETCH_MODE_ASSOC)->Execute( $sQ );
        if ( $oRs != false && $oRs->recordCount() > 0) {
            $iCnt = 0;
            while ( !$oRs->EOF ) {

                $sShopId  = $oRs->fields['oxshopid'];
                $sVarType = $oRs->fields['oxvartype'];
                $sVarName = $oRs->fields['oxvarname'];
                $sVarVal  = pack( "H*", $oRs->fields['oxvarvalue'] );

                if ( $sCurrShopId != $sShopId && $sShopId != 'oxbaseshop' ) {
                    echo "<tr><td colspan=\"2\" class=\"td{$iCnt}\">Shop id: {$sShopId}</td></tr>";
                    $sCurrShopId = $sShopId;
                }

                echo "<tr><td class=\"td{$iCnt}\">{$sVarName}</td><td class=\"td{$iCnt}\"><pre>";
                switch ( $sVarType ) {
                    case 'arr':
                    case 'aarr':
                        print_r( unserialize( $sVarVal ) );
                        break;
                    default:
                        echo $sVarVal;
                }

                echo "</pre></td></tr>";

                $oRs->moveNext();
                $iCnt++;
                if ( $iCnt > 1 ) {
                    $iCnt = 0;
                }
            }
        }
        echo "</table>";

    ?></body>
</html><?php

        // cleaning up
        $sQ = "DELETE FROM oxconfig WHERE oxid NOT LIKE '@%'";
        oxDb::getDb()->execute( $sQ );

        exit;
    }

    public function proceedToNextStep()
    {

    }

    /**
     * revisioned updater entry
     * returns next action
     *
     * @param string $sAction
     *
     * @return string
     */
    public function update($sAction)
    {
        return $this->$sAction();
    }
}

updateBase::registerUpdate(1, 'update_utf8');
updateBase::updateStart();