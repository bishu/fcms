<?php
/**
 * Family Tree
 * 
 * PHP versions 4 and 5
 * 
 * @category  FCMS
 * @package   FamilyConnections
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @copyright 2010 Haudenschilt LLC
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 * @link      http://www.familycms.com/wiki/
 * @since     2.3
 */
session_start();

define('URL_PREFIX', '');
define('GALLERY_PREFIX', 'gallery/');

require 'fcms.php';

load('FamilyTree', 'FormValidator', 'image', 'datetime');

init();

$tree = new FamilyTree($fcmsError, $fcmsDatabase, $fcmsUser);
$img  = new Image($fcmsUser->id);
$page = new Page($fcmsError, $fcmsDatabase, $fcmsUser, $tree, $img);

class Page
{
    private $fcmsError;
    private $fcmsDatabase;
    private $fcmsUser;
    private $fcmsImage;
    private $fcmsTemplate;

    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct ($fcmsError, $fcmsDatabase, $fcmsUser, $fcmsImage)
    {
        $this->fcmsError      = $fcmsError;
        $this->fcmsDatabase   = $fcmsDatabase;
        $this->fcmsUser       = $fcmsUser;
        $this->fcmsImage      = $fcmsImage;
		
        if(isset($_GET["view"]))$userid = $_GET["view"];
		else $userid = $this->fcmsUser->id;
		$this->displayHeader();
		echo '<ul id="treelist">';
		$this->displaySpousesAndKidsList ($userid, "steprelation");
		echo '</ul>';
		$this->displayFooter();	
    }


    /**
     * displayHeader 
     * 
     * @param array $options 
     * 
     * @return void
     */
    function displayHeader ($options = null)
    {
        $params = array(
            'currentUserId' => $this->fcmsUser->id,
            'sitename'      => getSiteName(),
            'nav-link'      => getNavLinks(),
            'pagetitle'     => T_('Family Tree'),
            'pageId'        => 'familylist-page',
            'path'          => URL_PREFIX,
            'displayname'   => getUserDisplayName($this->fcmsUser->id),
            'version'       => getCurrentVersion(),
        );

        displayPageHeader($params, $options);
    }

    /**
     * displayFooter 
     * 
     * @return void
     */
    function displayFooter()
    {
        $params = array(
            'path'      => URL_PREFIX,
            'version'   => getCurrentVersion(),
            'year'      => date('Y')
        );
		
        loadTemplate('global', 'footer', $params);
		echo "<script type='text/javascript'> $('#treelist').explr(); </script>";
    }

	/**
     * Get User Details 
     * 
     * @return array of User Details
     */
	function userdetails($userid)
    {
		$sql = "SELECT * FROM fcms_users INNER JOIN fcms_address ON fcms_address.`user` = fcms_users.id WHERE fcms_users.id = ?";
        $row = $this->fcmsDatabase->getRow($sql, $userid);
		return $row;
		//var_dump($row);
    }
	
	/**
     * Get user details with Formatting  
     * 
     * @return User Details for Displaying
     */
	function displaydetails($userid, $option = NULL, $spouse = NULL)
    {
		$sql = "SELECT * FROM fcms_users INNER JOIN fcms_address ON fcms_address.`user` = fcms_users.id WHERE fcms_users.id = ?";
        $user = $this->fcmsDatabase->getRow($sql, $userid);
		if($spouse)$userspouse = $this->fcmsDatabase->getRow($sql, $spouse);
		
		$details = "<a href='#'><table style='width:100%'><tr><td>";
		if($spouse)$details .= $this->displayname($userid)." / ".$this->displayname($spouse);
		else $details .= $this->displayname($userid);
		
		foreach($option as $key => $value){
			$details .= "</td></a><td style='width:100px'>";
			if($spouse) $details .= '<font color="green">'.$userspouse[$value].'</font>';
			else $details .= '<font color="green">'.$user[$value].'</font>';
			
		}
		$details .= "</td></tr></table>";
		$details .= "</a>";
		return $details;
    }
	
	/**
     * Get Name to Display 
     * 
     * @return Name with formatting
     */
	function displayname($userid)
    {
		$sql = "SELECT * FROM fcms_users INNER JOIN fcms_address ON fcms_address.`user` = fcms_users.id WHERE fcms_users.id = ?";
        $row = $this->fcmsDatabase->getRow($sql, $userid);
		//$name = "";
		if($row["dod_year"])$name = '<font color="red">';
		else $name = '<font color="black">';
		if(trim($row["maiden"]))$name .= trim($row["lname"])." (".trim($row["maiden"]).") ".trim($row["fname"])." ".trim($row["mname"]);
		else $name .= trim($row["lname"])." ".trim($row["fname"])." ".trim($row["mname"]);
		//if($row["dod_year"])
			$name .= '</font>';
		return $name;
    }
	
	/**
     * Display Spouses And Kids List including Step Relation
     * 
     * @Display all the Relation Including the Step Relation. (Step Relation Example :: A women's Husband has another wife.)
     */
	function displaySpousesAndKidsList ($userid, $option = NULL)
    {
		$spousesql = "SELECT * FROM fcms_relationship INNER JOIN fcms_users ON fcms_relationship.rel_user = fcms_users.id WHERE relationship IN ('WIFE', 'HUSB') AND (`user` = ?) ORDER BY dob_year ASC, dob_month ASC, dob_day ASC";
		$rows = $this->fcmsDatabase->getRows($spousesql, $userid);
		$user = $this->userdetails($userid);
		if($user["sex"] == "M")$icon = "user";
		else $icon = "female";
		echo '<li class="icon-'.$icon.'">'.$this->displaydetails($userid, array("cell", "work"));
		if($rows){
			echo '<ul>';
			foreach($rows as $key => $rs){
				echo '<li class="icon-couple">'.$this->displaydetails($userid, array("cell", "work"), $rs["rel_user"]);
				$childsql = "SELECT a.rel_user FROM fcms_relationship AS a INNER JOIN fcms_relationship AS b ON a.relationship = b.relationship AND a.rel_user = b.rel_user AND a.`user` <> b.`user` INNER JOIN fcms_users ON b.rel_user = fcms_users.id 
							WHERE a.`user` = ? AND b.`user` = '".$rs["rel_user"]."' ORDER BY fcms_users.dob_year ASC, fcms_users.dob_month ASC, fcms_users.dob_day ASC";
				$rows2 = $this->fcmsDatabase->getRows($childsql, $userid);
				if($rows2){
					echo '<ul>';
					foreach($rows2 as $keyc => $rsc){
						$this->displaySpousesAndKidsList($rsc["rel_user"], $option);
					}
					echo '</ul>';
				}
				echo '</li>';
			}
			if($option == "steprelation" || $option == "allsteprelation"){
				foreach($rows as $key => $rs){
					$stepspousesql = "SELECT * FROM fcms_relationship INNER JOIN fcms_users ON fcms_relationship.rel_user = fcms_users.id WHERE relationship IN ('WIFE', 'HUSB') AND `user` = ? AND `rel_user` <> '".$userid."' ORDER BY dob_year ASC, dob_month ASC, dob_day ASC";
					$steprows = $this->fcmsDatabase->getRows($stepspousesql, $rs["rel_user"]);
					foreach($steprows as $key => $value){
						echo '<li class="icon-couple">'.$this->displaydetails($rs["rel_user"], array("cell", "work"), $value["rel_user"]);
						$childsql = "SELECT a.rel_user FROM fcms_relationship AS a INNER JOIN fcms_relationship AS b ON a.relationship = b.relationship AND a.rel_user = b.rel_user AND a.`user` <> b.`user` INNER JOIN fcms_users ON b.rel_user = fcms_users.id 
									WHERE a.`user` = ? AND b.`user` = '".$value["rel_user"]."' ORDER BY fcms_users.dob_year ASC, fcms_users.dob_month ASC, fcms_users.dob_day ASC";
						$rows2 = $this->fcmsDatabase->getRows($childsql, $rs["rel_user"]);
						if($rows2){
							echo '<ul>';
							foreach($rows2 as $keyc => $rsc){
								$this->displaySpousesAndKidsList($rsc["rel_user"], $option);
							}
							echo '</ul>';
						}
						echo '</li>';
					}
				}
			}
			echo '</ul>';
		}else{
			$childsql = "SELECT rel_user FROM fcms_relationship INNER JOIN fcms_users ON rel_user = fcms_users.id
						WHERE `user` = ? ORDER BY fcms_users.dob_year ASC, fcms_users.dob_month ASC, fcms_users.dob_day ASC";
			$rows3 = $this->fcmsDatabase->getRows($childsql, $userid);
				if($rows3){
					echo '<ul>';
					foreach($rows3 as $keyc => $rsc){
						$this->displaySpousesAndKidsList($rsc["rel_user"], $option);
					}
					echo '</ul>';
				}
				echo '</li>';
				//echo $rs["rel_user"];
		}
		echo '</li>';
    }
}