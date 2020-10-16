<?php
/**
 * authNexusUserIDPlugin Plugin for LimeSurvey
 * Force user to have a valid Nexus Mods ID before continuing with survey.
 *
 * @author Reneer <reneerbot@gmail.com>
 * @copyright 2019-2020 Reneer.
 * @License: MIT, see LICENSE.txt
*/

/*
class authNexusIDSurveyList extends SurveysController {
	
}
*/

class authNexusUserIDPlugin extends PluginBase {
    protected $storage = 'DbStorage';

    static protected $description = 'A plugin to force users to ID via Nexus Mods.';
    static protected $name = 'authNexusUserIDPlugin';

	//use SurveysController;

	/*
    protected $settings = array(
        'browsercode' => array(
            'type' => 'string',
            'label' => 'The question code to be filled with browser information.',
            'default' => 'browser'
        ),
        'browsernamecode' => array(
            'type' => 'string',
            'label' => 'The question code to be filled with browser name.',
            'default' => 'browsername'
        ),
        'browserversioncode' => array(
            'type' => 'string',
            'label' => 'The question code to be filled with browser version.',
            'default' => 'browserversion'
        ),
        'active' => array(
            'type' => 'boolean',
            'label' => 'Use it by default.',
            'default' => true
        ),
        'questioncodeexample' => array(
            'type' => 'info',
            'content' => '<div class="alert alert-info">You have an survey exemple file in the plugin directory (limesurvey_survey_browser.lss).</div>',
        ),
    );
	*/

    public function init() {
        $this->subscribe('beforeSurveyPage');
		$this->subscribe('beforeControllerAction');
		$this->subscribe('afterSurveyComplete');
    }

	public function afterSurveyComplete()
	{
		$oEvent = $this->getEvent();
		$iSurveyId = $oEvent->get('surveyId');

		session_start();

		$survey_id = $iSurveyId;

		$oSurvey = Survey::model()->findByPk($iSurveyId);		
		
		$db = Yii::app()->db;		
		
		$sqlcommand = "INSERT INTO tokens_unms_" . $iSurveyId . " VALUES ('" . $_SESSION['userid'] . "');";
		$db->createCommand($sqlcommand)->execute();
		
		session_write_close();
	}

	public function UNMS_userStats_actionAction($surveyid, $language = null)
	{
        $sLanguage = $language;
        $survey = Survey::model()->findByPk($surveyid);
        $this->sLanguage = $language;
        $iSurveyID = (int) $survey->sid;
        $this->iSurveyID = $survey->sid;
        //$postlang = returnglobal('lang');
        //~ Yii::import('application.libraries.admin.progressbar',true);
        Yii::app()->loadHelper("userstatistics");
        Yii::app()->loadHelper('database');
        Yii::app()->loadHelper('surveytranslator');
        $data = array();
        if (!isset($iSurveyID)) {
            $iSurveyID = returnGlobal('sid');
        } else {
            $iSurveyID = (int) $iSurveyID;
        }
        if (!$iSurveyID) {
            //This next line ensures that the $iSurveyID value is never anything but a number.
            throw new CHttpException(404, 'You have to provide a valid survey ID.');
        }
        $actresult = Survey::model()->findAll('sid = :sid AND active = :active', array(':sid' => $iSurveyID, ':active' => 'Y')); //Checked
        if (count($actresult) == 0) {
            throw new CHttpException(404, 'You have to provide a valid survey ID.');
        } else {
            $surveyinfo = getSurveyInfo($iSurveyID);
            // CHANGE JSW_NZ - let's get the survey title for display
            $thisSurveyTitle = $surveyinfo["name"];
            // CHANGE JSW_NZ - let's get css from individual template.css - so define path
            $thisSurveyCssPath = getTemplateURL($surveyinfo["template"]);
            if ($surveyinfo['publicstatistics'] != 'Y') {
                throw new CHttpException(404, 'The public statistics for this survey are deactivated.');
            }
            //check if graphs should be shown for this survey
            if ($survey->isPublicGraphs) {
                $publicgraphs = 1;
            } else {
                $publicgraphs = 0;
            }
        }
        //we collect all the output within this variable
        $statisticsoutput = '';
        //for creating graphs we need some more scripts which are included here
        //True -> include
        //False -> forget about charts
        if (isset($publicgraphs) && $publicgraphs == 1) {
            require_once(APPPATH.'third_party/pchart/pChart.class.php');
            require_once(APPPATH.'third_party/pchart/pData.class.php');
            require_once(APPPATH.'third_party/pchart/pCache.class.php');
            $MyCache = new pCache(Yii::app()->getConfig("tempdir").DIRECTORY_SEPARATOR);
            //$currentuser is created as prefix for pchart files
            if (isset($_SERVER['REDIRECT_REMOTE_USER'])) {
                $currentuser = $_SERVER['REDIRECT_REMOTE_USER'];
            } else if (session_id()) {
                $currentuser = substr(session_id(), 0, 15);
            } else {
                $currentuser = "standard";
            }
        }
        // Set language for questions and labels to base language of this survey
        if ($sLanguage == null || !in_array($sLanguage, Survey::model()->findByPk($iSurveyID)->getAllLanguages())) {
            $sLanguage = Survey::model()->findByPk($iSurveyID)->language;
        } else {
            $sLanguage = sanitize_languagecode($sLanguage);
        }
        //set survey language for translations
        SetSurveyLanguage($iSurveyID, $sLanguage);
        //Create header
        $condition = false;
        $sitename = Yii::app()->getConfig("sitename");
        $data['surveylanguage'] = $sLanguage;
        $data['sitename'] = $sitename;
        $data['condition'] = $condition;
        $data['thisSurveyCssPath'] = $thisSurveyCssPath;
        /*
         * only show questions where question attribute "public_statistics" is set to "1"
         */
        $query = "SELECT q.* , group_name, group_order FROM {{questions}} q, {{groups}} g, {{question_attributes}} qa
                    WHERE g.gid = q.gid AND g.language = :lang1 AND q.language = :lang2 AND q.sid = :surveyid AND q.qid = qa.qid AND q.parent_qid = 0 AND qa.attribute = 'public_statistics'";
        $databasetype = Yii::app()->db->getDriverName();
        if ($databasetype == 'mssql' || $databasetype == "sqlsrv" || $databasetype == "dblib") {
            $query .= " AND CAST(CAST(qa.value as varchar) as int)='1'\n";
        } else {
            $query .= " AND qa.value='1'\n";
        }
        //execute query
        $result = Yii::app()->db->createCommand($query)->bindParam(":lang1", $sLanguage, PDO::PARAM_STR)->bindParam(":lang2", $sLanguage, PDO::PARAM_STR)->bindParam(":surveyid", $iSurveyID, PDO::PARAM_INT)->queryAll();
        //store all the data in $rows
        $rows = $result;
        //SORT IN NATURAL ORDER!
        usort($rows, 'groupOrderThenQuestionOrder');
        //put the question information into the filter array
        $filters = array();
        foreach ($rows as $row) {
            //store some column names in $filters array
            $filters[] = array($row['qid'],
            $row['gid'],
            $row['type'],
            $row['title'],
            $row['group_name'],
            flattenText($row['question']));
        }
        //number of records for this survey
        $totalrecords = 0;
        //count number of answers
        $query = "SELECT count(*) FROM ".$survey->responsesTableName;
        //if incompleted answers should be filtert submitdate has to be not null
        //this setting is taken from config-defaults.php
        if (Yii::app()->getConfig("filterout_incomplete_answers") == true) {
            $query .= " WHERE ".$survey->responsesTableName.".submitdate is not null";
        }
        $result = Yii::app()->db->createCommand($query)->queryAll();
        //$totalrecords = total number of answers
        foreach ($result as $row) {
            $totalrecords = reset($row);
        }
        //...while this is the array from copy/paste which we don't want to replace because this is a nasty source of error
        $allfields = array();
        //---------- CREATE SGQA OF ALL QUESTIONS WHICH USE "PUBLIC_STATISTICS" ----------
        /*
         * let's go through the filter array which contains
         *     ['qid'],
         ['gid'],
         ['type'],
         ['title'],
         ['group_name'],
         ['question'];
                 */
        $currentgroup = '';
        // use to check if there are any question with public statistics
        if (isset($filters)) {
            $allfields = $this->createSGQA($filters);
        }// end if -> for removing the error message in case there are no filters
        $summary = $allfields;
        // Get the survey inforamtion
        $thissurvey = getSurveyInfo($surveyid, $sLanguage);
        //SET THE TEMPLATE DIRECTORY
        //---------- CREATE STATISTICS ----------
        //some progress bar stuff
        // Create progress bar which is shown while creating the results
        //~ $prb = new ProgressBar();
        //~ $prb->pedding = 2;    // Bar Pedding
        //~ $prb->brd_color = "#404040 #dfdfdf #dfdfdf #404040";    // Bar Border Color
        //~ $prb->setFrame();    // set ProgressBar Frame
        //~ $prb->frame['left'] = 50;    // Frame position from left
        //~ $prb->frame['top'] =     80;    // Frame position from top
        //~ $prb->addLabel('text','txt1',gT("Please wait ..."));    // add Text as Label 'txt1' and value 'Please wait'
        //~ $prb->addLabel('percent','pct1');    // add Percent as Label 'pct1'
        //~ $prb->addButton('btn1',gT('Go back'),'?action=statistics&amp;sid='.$iSurveyID);    // add Button as Label 'btn1' and action '?restart=1'
        //~ $prb->show();    // show the ProgressBar
        //~ // 1: Get list of questions with answers chosen
        //~ //"Getting Questions and Answer ..." is shown above the bar
        //~ $prb->setLabelValue('txt1',gT('Getting questions and answers ...'));
        //~ $prb->moveStep(5);
        // creates array of post variable names
        $postvars = array();
        for (reset($_POST); $key = key($_POST); next($_POST)) {
            $postvars[] = $key;
        }
        $data['thisSurveyTitle'] = $thisSurveyTitle;
        $data['totalrecords'] = $totalrecords;
        $data['summary'] = $summary;
        //show some main data at the beginnung
        // CHANGE JSW_NZ - let's allow html formatted questions to show
        //push progress bar from 35 to 40
        $process_status = 40;
        //Show Summary results
        if (isset($summary) && !empty($summary)) {
            //"Generating Summaries ..." is shown above the progress bar
            //~ $prb->setLabelValue('txt1',gT('Generating summaries ...'));
            //~ $prb->moveStep($process_status);
            //let's run through the survey // Fixed bug 3053 with array_unique
            $runthrough = array_unique($summary);
            //loop through all selected questions
            foreach ($runthrough as $rt) {
                //update progress bar
                if ($process_status < 100) {
                    $process_status++;
                }
                //~ $prb->moveStep($process_status);
            }    // end foreach -> loop through all questions
            $helper = new userstatistics_helper();
            $statisticsoutput .= $helper->generate_statistics($iSurveyID, $summary, $summary, $publicgraphs, 'html', null, $sLanguage, false);
        }    //end if -> show summary results
        $data['statisticsoutput'] = $statisticsoutput;
        //done! set progress bar to 100%
        if (isset($prb)) {
            //~ $prb->setLabelValue('txt1',gT('Completed'));
            //~ $prb->moveStep(100);
            //~ $prb->hide();
        }
        Yii::app()->getClientScript()->registerScriptFile(Yii::app()->getConfig('generalscripts').'statistics_user.js');
        $this->layout = "public";
        $this->render('/statistics_user_view', $data);
        //Delete all Session Data
        Yii::app()->session['finished'] = true;
	}

	public function UNMS_actionPublicStatsList($lang = null)
	{	
		session_start();
	
		if (isset($_SESSION["userid"]) == false || sizeof($_SESSION["userid"]) <= 0)
		{
			print "There was an error (004) logging you in. Please try again. Redirecting you to the homepage in 5 seconds.";
			print "<script>setTimeout(\"window.location.href = 'https://www.psychfox.com'\",5000);</script>";
			session_write_close();			
			die();
		}
	
		if (!empty($lang)) {
			// Control is a real language , in restrictToLanguages ?
			App()->setLanguage($lang);
		} else {
			App()->setLanguage(App()->getConfig('defaultlang'));
		}
		$oTemplate       = Template::model()->getInstance(getGlobalSetting('defaulttheme'));
		$this->sTemplate = $oTemplate->sTemplateName;
		$aData = array(
				'publicSurveys'     => Survey::model()->active()->open()->public()->with('languagesettings')->findAll(),
				'futureSurveys'     => Survey::model()->active()->registration()->public()->with('languagesettings')->findAll(),
				'oTemplate'         => $oTemplate,
				'sSiteName'         => Yii::app()->getConfig('sitename'),
				'sSiteAdminName'    => Yii::app()->getConfig("siteadminname"),
				'sSiteAdminEmail'   => Yii::app()->getConfig("siteadminemail"),
				'bShowClearAll'     => false,
				'surveyls_title'    => Yii::app()->getConfig('sitename')
			);
			
		$publicsurveylist = $aData['publicSurveys'];
		
		//print_r($publicsurveylist);
		
		for ($survey = 0; $survey < sizeof($publicsurveylist); $survey++)
		{			
			if ($publicsurveylist[$survey] == null)
			{
				continue;
			}
			
			$gsid = $publicsurveylist[$survey]->gsid;
			
			// scripts/lime/index.php/statistics_user/
			
			print "<a href='/scripts/lime/index.php/statistics_user/" . $publicsurveylist[$survey]->sid . "'>SurveyID</a><br/>";
			
			/*
			if ($gsid == 1)
			{
				// survey that is set with the default survey group
				// so we don't do anything to it.			
			} else {
				// survey that is not the default survey group.
				if (isset($_SESSION["ismodauthor"]) == false || $_SESSION["ismodauthor"] == false)
				{
					// current user is NOT a mod author.
					// so we remove this survey from the list.
					array_splice($publicsurveylist, $survey, 1);
				}
			}
			*/
		}
			
		session_write_close();			
			
		/*
		$aData['alanguageChanger']['show'] = false;
		$alanguageChangerDatas = getLanguageChangerDatasPublicList(App()->language);
		if ($alanguageChangerDatas) {
			$aData['alanguageChanger']['show']  = true;
			$aData['alanguageChanger']['datas'] = $alanguageChangerDatas;
		}
		Yii::app()->clientScript->registerScriptFile(Yii::app()->getConfig("generalscripts").'nojs.js', CClientScript::POS_HEAD);
		Yii::app()->twigRenderer->renderTemplateFromFile("layout_survey_list.twig", array('aSurveyInfo'=>$aData), false);
		
		//return false;
		*/
	}

	public function UNMS_actionPublicList($lang = null)
	{	
		session_start();
	
		if (isset($_SESSION["userid"]) == false || sizeof($_SESSION["userid"]) <= 0)
		{
			print "There was an error (004) logging you in. Please try again. Redirecting you to the homepage in 5 seconds.";
			print "<script>setTimeout(\"window.location.href = 'https://www.psychfox.com'\",5000);</script>";
			session_write_close();			
			die();
		}
	
		if (!empty($lang)) {
			// Control is a real language , in restrictToLanguages ?
			App()->setLanguage($lang);
		} else {
			App()->setLanguage(App()->getConfig('defaultlang'));
		}
		$oTemplate       = Template::model()->getInstance(getGlobalSetting('defaulttheme'));
		$this->sTemplate = $oTemplate->sTemplateName;
		$aData = array(
				'publicSurveys'     => Survey::model()->active()->open()->public()->with('languagesettings')->findAll(),
				'futureSurveys'     => Survey::model()->active()->registration()->public()->with('languagesettings')->findAll(),
				'oTemplate'         => $oTemplate,
				'sSiteName'         => Yii::app()->getConfig('sitename'),
				'sSiteAdminName'    => Yii::app()->getConfig("siteadminname"),
				'sSiteAdminEmail'   => Yii::app()->getConfig("siteadminemail"),
				'bShowClearAll'     => false,
				'surveyls_title'    => Yii::app()->getConfig('sitename')
			);
			
		$publicsurveylist = $aData['publicSurveys'];
			
		for ($survey = 0; $survey < sizeof($publicsurveylist); $survey++)
		{			
			if ($publicsurveylist[$survey] == null)
			{
				continue;
			}
			
			$gsid = $publicsurveylist[$survey]->gsid;
			
			if ($gsid == 1)
			{
				// survey that is set with the default survey group
				// so we don't do anything to it.			
			} else {
				// survey that is not the default survey group.
				if (isset($_SESSION["ismodauthor"]) == false || $_SESSION["ismodauthor"] == false)
				{
					// current user is NOT a mod author.
					// so we remove this survey from the list.
					array_splice($publicsurveylist, $survey, 1);
				}
			}
		}
		$aData['publicSurveys'] = $publicsurveylist;
			
		session_write_close();			
			
		$aData['alanguageChanger']['show'] = false;
		$alanguageChangerDatas = getLanguageChangerDatasPublicList(App()->language);
		if ($alanguageChangerDatas) {
			$aData['alanguageChanger']['show']  = true;
			$aData['alanguageChanger']['datas'] = $alanguageChangerDatas;
		}
		Yii::app()->clientScript->registerScriptFile(Yii::app()->getConfig("generalscripts").'nojs.js', CClientScript::POS_HEAD);
		Yii::app()->twigRenderer->renderTemplateFromFile("layout_survey_list.twig", array('aSurveyInfo'=>$aData), false);
		
		//return false;
	}

	public function beforeControllerAction()
	{
		// 'publicSurveys'     => Survey::model()->active()->open()->public()->with('languagesettings')->findAll();
		
        $oEvent = $this->getEvent();	
		
		$controller = $oEvent->get('controller');
		$action = $oEvent->get('action');
		$subaction = $oEvent->get('subaction');
		
		if ($controller == "Statistics_user" && $action =="")
		{
			
		}
		
		if ($controller == "surveys" && $action == "publicList" && !isset($_GET['showstats']))
		{
			$oEvent->set('run',false);			
			$this->UNMS_actionPublicList();
		}
		if ($controller == "surveys" && $action == "publicList" && isset($_GET['showstats']))
		{
			$oEvent->set('run',false);						
			$this->UNMS_actionPublicStatsList();
		}
	}

    public function beforeSurveyPage()
    {
        $oEvent = $this->getEvent();
        $iSurveyId = $oEvent->get('surveyId');

        // code here is to check that user has a been IDed by the system.

		session_start();

		$survey_id = $iSurveyId;

		$oSurvey = Survey::model()->findByPk($iSurveyId);
		
		$bActive = $this->get('active', 'Survey', $iSurveyId, '');
		$gsid = $this->get('gsid', 'Survey', $iSurveyId, '');
		
		$userinfo = $this->api->getCurrentUser();
		
		$fields = array(
			'token' => "string(100)"
		);
		
		$db = Yii::app()->db;
		
		$tableSchema = $db->schema->getTable("tokens_unms_{$iSurveyId}",true);
		
		if ($tableSchema === null)
		{
			$db->createCommand()->createTable("tokens_unms_{$iSurveyId}", $fields);
		}
		
		if ($tableSchema != null)
		{
			$sqlcommand = "SELECT * FROM tokens_unms_" . $iSurveyId . " WHERE token LIKE '%" . $_SESSION['userid'] . "%';";
			$resultarray = $db->createCommand($sqlcommand)->queryAll();
			if (sizeof($resultarray) > 0)
			{
				// we have seen this userid before!
				print "We're sorry, but it seems that you have already completed this survey. Redirecting you back to the survey list page in 5 seconds.";
				print "<script>setTimeout(\"window.location.href = 'https://www.psychfox.com/scripts/lime/index.php'\",5000);</script>";
				session_write_close();
				die();
			}
		}
		
		if (isset($gsid) == true)
		{
			if ($userinfo == false && $gsid != 1 && (isset($_SESSION["ismodauthor"]) == false || $_SESSION["ismodauthor"] == false))
			{		
				// Survey is not default survey group (1), so mod author survey group likely.		
				// bad, non-mod author likely trying to access mod author only survey!
				print "You do not have access to this survey. Redirecting you to the survey list page in 5 seconds.";
				print "<script>setTimeout(\"window.location.href = 'https://www.psychfox.com/scripts/lime/index.php'\",5000);</script>";
				session_write_close();
				die();
			}
		} else {
			// bad, should not happen.
			print "There was an error (003) logging you in. Please try again. Redirecting you to the homepage in 5 seconds.";
			print "<script>setTimeout(\"window.location.href = 'https://www.psychfox.com'\",5000);</script>";
			session_write_close();
			die();			
		}
		
		if ($userinfo != false || (isset($_SESSION["userid"]) == true && sizeof($_SESSION["userid"]) > 0))
		{
			// cool, continue on.
		} else {
			if ($userinfo == false || $userinfo == null)
			{
				print "There was an error (001) logging you in. Please try again. Redirecting you to the homepage in 5 seconds.";
				print "<script>setTimeout(\"window.location.href = 'https://www.psychfox.com'\",5000);</script>";
				session_write_close();
				die();
			}
		}		
    }
}

