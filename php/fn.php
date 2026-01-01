<?php
function password_encrypt($password)
	{
		$hash_format = "$2y$10$" ;
		$salt_length = 22 ;
		$salt = salt_generator($salt_length);
		$format_salt = $hash_format . $salt ;
		$hash = crypt($password , $format_salt) ;
		return $hash;
	}

	function salt_generator($salt_length)
	{
		$unique_rand_str = md5(uniqid(mt_rand(),true)) ;
		$base64_str = base64_encode($unique_rand_str);
		$modified_base64_str = str_replace("+", ".", $base64_str);
		$salt = substr($modified_base64_str, 0,$salt_length);

		return $salt;
	}