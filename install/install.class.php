<?php /*
    This file is part of CoDev-Timetracking.

    CoDev-Timetracking is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Foobar is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
*/ ?>

<?php


include_once 'project.class.php';
include_once 'team.class.php';
include_once 'config.class.php';


class Install {

   const FILENAME_MYSQL_CONFIG = "../include/mysql_config.inc.php";
   //const FILENAME_MYSQL_CONFIG = "/tmp/mysql_config.inc.php";

   const JOB_DEFAULT_SIDETASK = 1; // REM: N/A     job_id = 1, created by SQL file
   const JOB_SUPPORT          = 2; // REM: Support job_id = 2, created by SQL file

   private $fieldList;

   // --------------------------------------------------------
   public function __construct()
   {
   	// get existing Mantis custom fields
      $this->fieldList = array();

   }


   // --------------------------------------------------------
   /**
    * Checks if DB connection is OK
    * @param unknown_type $db_mantis_host
    * @param unknown_type $db_mantis_user
    * @param unknown_type $db_mantis_pass
    * @param unknown_type $db_mantis_database
    *
    * @return NULL if OK, or an error message.
    */
   public function checkDBConnection($db_mantis_host     = 'localhost',
                                     $db_mantis_user     = 'codev',
                                     $db_mantis_pass     = '',
                                     $db_mantis_database = 'bugtracker') {

      $bugtracker_link = mysql_connect($db_mantis_host, $db_mantis_user, $db_mantis_pass);
      if (!$bugtracker_link) {
      	return ("Could not connect to database: ". mysql_error());
      }

      $db_selected = mysql_select_db($db_mantis_database);
      if (!$db_selected) {
      	return ("Could not select database: ". mysql_error());
      }


      $query = "SELECT value FROM `mantis_config_table` WHERE config_id = 'database_version'";
      $result = mysql_query($query);
      if (!$result) {
         return ("Query failed : " . mysql_error());
      }
      $database_version  = (0 != mysql_num_rows($result)) ? mysql_result($result, 0) : -1;

      if (-1 == $database_version) {
      	return "Could not get mantis_config_table.database_version";
      }
      return NULL;
   }

   // --------------------------------------------------------
	/**
	 * updates mysql_config_inc.php with connection parameters
	 */
	public function createMysqlConfigFile($db_mantis_host     = 'localhost',
	                                      $db_mantis_user     = 'codev',
	                                      $db_mantis_pass     = '',
	                                      $db_mantis_database = 'bugtracker') {
      echo "create file ".self::FILENAME_MYSQL_CONFIG."<br/>";

      // create/overwrite file
      $fp = fopen(self::FILENAME_MYSQL_CONFIG, 'w');

      if (FALSE == $fp) {
      	echo "ERROR creating file ".self::FILENAME_MYSQL_CONFIG."<br/>";

      } else {
      	$stringData = "<?php\n";
      	$stringData .= "   // Mantis DB infomation.\n";
      	$stringData .= "   \$db_mantis_host      =  '$db_mantis_host';\n";
      	$stringData .= "   \$db_mantis_user      =  '$db_mantis_user';\n";
      	$stringData .= "   \$db_mantis_pass      =  '$db_mantis_pass';\n";
      	$stringData .= "   \$db_mantis_database  =  '$db_mantis_database';\n";
      	$stringData .= "?>\n";
      	fwrite($fp, $stringData);
      	fclose($fp);
      	echo "done<br/>";
      }
   }


   // --------------------------------------------------------
   /**
    *
    * @param $sqlFile
    */
	public function execSQLscript($sqlFile = "bugtracker_install.sql") {

      $requetes="";

      $sql=file($sqlFile);
      foreach($sql as $l){
         if (substr(trim($l),0,2)!="--"){ // remove comments
            $requetes .= $l;
         }
      }

      $reqs = split(";",$requetes);// identify single requests
      foreach($reqs as $req){
         if (!mysql_query($req) && trim($req)!="") {
            die("ERROR : ".$req." ---> ".mysql_error());
         }
      }
      echo "done<br/>";
	}

   // --------------------------------------------------------
	/**
	 * create a customField in Mantis (if not exist) & update codev_config_table
	 *
	 * ex: $install->createCustomField("TC", 0, "customField_TC");
	 *
	 * @param string $fieldName Mantis field name
	 * @param int $fieldType Mantis field type
	 * @param string $configId  codev_config_table.config_id
	 */
	public function createCustomField($fieldName, $fieldType, $configId, $default_value=NULL, $possible_values=NULL) {

      $access_level_r   = 10;
      $access_level_rw  = 25;
      $require_report   = 1;
      $require_update   = 1;
      $require_resolved = 0;
      $require_closed   = 0;
      $display_report   = 1;
      $display_update   = 1;
      $display_resolved = 0;
      $display_closed   = 0;


      //--------
      $query = "SELECT id, name FROM `mantis_custom_field_table`";
      $result = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      {
         $this->fieldList["$row->name"] = $row->id;
      }


		//--------
      $fieldId = $this->fieldList[$fieldName];
      if (!$fieldId) {
         $query2  = "INSERT INTO `mantis_custom_field_table` ".
                    "(`name`, `type` ,`access_level_r`,`access_level_rw` ,`require_report` ,`require_update` ,`display_report` ,`display_update` ,`require_resolved` ,`display_resolved` ,`display_closed` ,`require_closed` ";
         if ($possible_values) {
         	$query2 .= ", `possible_values`";
         }
         if ($default_value) {
            $query2 .= ", `default_value`";
         }
         $query2 .= ") VALUES ('$fieldName', '$fieldType', '$access_level_r', '$access_level_rw', '$require_report', '$require_update', '$display_report', '$display_update', '$require_resolved', '$display_resolved', '$display_closed', '$require_closed'";
         if ($possible_values) {
            $query2 .= ", '$possible_values'";
         }
         if ($default_value) {
            $query2 .= ", '$default_value'";
         }
         $query2 .= ");";

      	 #echo "DEBUG INSERT $fieldName --- query $query2 <br/>";

         $result2  = mysql_query($query2) or die("Query failed: $query2");
         $fieldId = mysql_insert_id();

         echo "custom field '$configId' created.<br/>";

      } else {
      	echo "custom field '$configId' already exists.<br/>";
      }

	  // add to codev_config_table
      Config::getInstance()->setValue($configId, $fieldId, Config::configType_int);


	}

   // --------------------------------------------------------
	/**
	 *
	 */
	public function createCustomFields() {

	  // Mantis customFields types
	  $mType_string  = 0;
      $mType_numeric = 1;
      $mType_enum    = 3;
      $mType_date    = 8;

      $this->createCustomField("TC",                               $mType_string,  "customField_TC");          // CoDev FDJ custom
      $this->createCustomField("Preliminary Est. Effort (ex ETA)", $mType_enum,    "customField_PrelEffortEstim", "none", "none|< 1 day|2-3 days|< 1 week|< 2 weeks|> 2 weeks");
      $this->createCustomField("Est. Effort (BI)",                 $mType_numeric, "customField_effortEstim");
      $this->createCustomField("Budget supp. (BS)",                $mType_numeric, "customField_addEffort");
      $this->createCustomField("Remaining (RAE)",                  $mType_numeric, "customField_remaining");
      $this->createCustomField("Dead Line",                        $mType_date,    "customField_deadLine");
      $this->createCustomField("FDL",                              $mType_string,  "customField_deliveryId");  // CoDev FDJ custom
      $this->createCustomField("Liv. Date",                        $mType_date,    "customField_deliveryDate");

	}



   // --------------------------------------------------------
   /**
    * create SideTasks Project and assign N/A Job
    *
    * @param unknown_type $projectName
    */
	public function createCommonSideTasksProject($projectName = "SideTasks", $projectDesc = "CoDev commonSideTasks Project") {

		// create project
		$projectid = Project::createSideTaskProject($projectName);

		if (-1 != $projectid) {

		   // --- update defaultSideTaskProject in codev_config_table
      	   Config::getInstance()->setValue(Config::id_defaultSideTaskProject, $projectid, Config::configType_int , $projectDesc);

           $stproj = ProjectCache::getInstance()->getProject($projectid);

           // --- add SideTaskProject Categories
      	   $stproj->addCategoryInactivity(T_("Inactivity"));
      	   $stproj->addCategoryProjManagement(T_("Project Management"));
           $stproj->addCategoryIncident(T_("Incident"));
           $stproj->addCategoryTools(T_("Tools"));
           $stproj->addCategoryWorkshop(T_("Team Workshop")); // TODO is this Cat relevant for CommonSideTaskProj ?

      		// --- assign SideTaskProject specific Job
      		#REM: 'N/A' job_id = 1, created by SQL file
      		$job_NA = self::JOB_DEFAULT_SIDETASK;
      		$query  = "INSERT INTO `codev_project_job_table` (`project_id`, `job_id`) VALUES ('$projectid', '$job_NA');";
      		$result = mysql_query($query) or die("Query failed: $query");
		}
      return $projectid;
	}



	/**
	 * create Admin team & add to codev_config_table
	 *
	 */
   public function createAdminTeam($name, $leader_id) {

   	  $now = time();
   	  $formatedDate  = date("Y-m-d", $now);
      $today = date2timestamp($formatedDate);

      // create admin team
   	  $teamId = Team::create($name, T_("CoDev admin team"), $leader_id, $today);

   	  if (-1 != $teamId) {
         // add to codev_config_table
   	     Config::getInstance()->setValue(Config::id_adminTeamId, $teamId, Config::configType_int);

   	     // add leader as member
   	     $adminTeam = new Team($teamId);
   	     $adminTeam->addMember($leader_id, $now, Team::accessLevel_dev);

         // add default SideTaskProject
         $adminTeam->addCommonSideTaskProject();

      }
      return $teamId;
   }

	function setConfigItems() {

      //Config::getInstance()->setValue(Config::id_astreintesTaskList, array(), Config::configType_array);

	}

	function checkReportsDir($codevReportsDir) {

	  // Note: the 'ERROR' token in return string will be parsed, so
	  //       do not remove it.

	  // if path does not exist, try to create it
	  if (FALSE == file_exists ($codevReportsDir)) {
	  	if (!mkdir($codevReportsDir, 0755, true)) {
           return(T_("ERROR").T_(": Could not create folder: $codevReportsDir"));
        }
	  }

	  // create a test file to check write access to the directory
	  $testFilename = $codevReportsDir . DIRECTORY_SEPARATOR . "test.txt";
	  $fh = fopen($testFilename, 'w');
	  if (FALSE == $fh) {
	  	return (T_("ERROR").T_(": could not create test file: $testFilename"));
	  }

	  // write something to the file
	  $stringData = date("Y-m-d G:i:s", time()) . " - This is a TEST file generated during CoDev installation, You can remove it.\n";
      if (FALSE == fwrite($fh, $stringData)) {
        fclose($fh);
	  	return (T_("ERROR").T_(": could not write to test file: $testFilename"));
      }

	  fclose($fh);
	  return "SUCCESS ! Please check that the following test file has been created:<br/><span style='font-family: sans-serif'>$testFilename</span>";
	}


} // class

?>