<?php 

	/**
	 * LifeChurch API
	 * @author Daniel Boorn - daniel.boorn@gmail.com
	 * @copyright Daniel Boorn
	 * @license Creative Commons Attribution-NonCommercial 3.0 Unported (CC BY-NC 3.0)
	 * @namespace F1
	 */
	
	/**
	 * The class uses a JSON api_path.js file that defines the API endpoints and paths.
	 * This class is chainable! Please see examples before use.
	 *
	 */

	//phpQuery only requrired for those who wish to work with xml
	require_once('vendor/com.github.tobiaszcudnik/phpquery.php');
	require_once('vendor/com.rapiddigitalllc/lifechurch/api.php');
	
	session_start();
	//unset($_SESSION);
	//var_dump($_SESSION);
	
	try{
		$lc = \LifeChurch\API::forge();//2nd party auto sign-in (session based)
		# Show available chain paths by id
		//var_dump($lc->paths);
	
		# Example - get my account information.
		
		$user = $lc->json()->user()->myaccount()->get();
		echo "Name: {$user['first_name']} {$user['last_name']}";
		var_dump($user);
		
		
		
		# Example - get user account information (admin only)
		/*
		echo "Name: {$user['first_name']} {$user['last_name']}";
		$user = $lc->json()->user(12)->show()->get();
		var_dump($user);
		*/
		
		
	}catch(\LifeChurch\Exception $e){
		//var_dump($e);
		echo $e->response;
	}


?>