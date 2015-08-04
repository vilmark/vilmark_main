<?php

function M_get_charset_collate() {
	global $wpdb;
	$charset_collate = '';
	if ( !empty( $wpdb->charset ) ) {
		$charset_collate = " DEFAULT CHARACTER SET " . $wpdb->charset;
	}
	if ( !empty( $wpdb->collate ) ) {
		$charset_collate .= " COLLATE " . $wpdb->collate;
	}

	return $charset_collate;
}

function M_Upgrade( $from = false ) {
	switch ( $from ) {
		default:
		case 0:
			M_Createtables();

		case 1:
		case 2:
			M_Alterfor2();

		case 3:
			M_Alterfor3();

		case 4:
		case 5:
			M_Alterfor4();

		case 6:
			M_Alterfor5();

		case 7:
			M_Alterfor6();

		case 8:
		case 9:
			M_Alterfor10();

		case 10:
			M_Alterfor11();

		case 11:
			M_Alterfor12();

		case 12:
		case 13:
			M_Alterfor14();

		case 14:
			M_Alterfor15();

		case 15:
		case 16:
		case 17:
			M_Alterfor16();

		case 18:
		case 19:
		case 20:
		case 21:
			M_Alterfor18();
		case 22:
			M_Alterfor22();

			M_repair_tables( false );
			break;
	}
}


function M_Alterfor22() {
	global $wpdb;

	$table = membership_db_prefix( $wpdb, 'membership_rules' );
	$sql = $wpdb->prepare( "SELECT * FROM `$table` WHERE `rule_area` IN ( %s )",
							"menu','posts','pages','categories" );
	$sql = str_replace("\'", "'", $sql );

	$rows = $wpdb->get_results( $sql );

	foreach ( $rows as $row ) {

		$level = $row->level_id;

		$rule_ive = $row->rule_ive;
		$rule_area = $row->rule_area;
		$rule_value = $row->rule_value;

		if( 'menu' == $rule_area ) {
			$rule_value = M_Alter_convert_rule( $rule_value, true );
		} else {
			$rule_value = M_Alter_convert_rule( $rule_value );
		}

		$sql = $wpdb->prepare("UPDATE `$table` SET `rule_value` = %s WHERE `level_id` = %d AND `rule_ive` = %s AND `rule_area` = %s",
							  $rule_value, $level, $rule_ive, $rule_area);
		$sql = str_replace('\"', '"' , $sql );
		$sql = str_replace("\'", "'", $sql );
		$result = $wpdb->query( $sql );
	}

}

function M_Alter_convert_rule( $rule, $special = false ) {
	$orig = unserialize($rule);
	$arr = $orig;
	$new_arr = array();
	if( ! is_array( array_pop( $arr ) ) ) {
		foreach( $orig as $old ) {
			$obj = array();
			$obj['id'] = $old;
			$obj['drip'] = 0;
			$obj['drip_unit'] = 'd';
			if( $special ) {
				$new_arr[$old] = $obj;
			} else {
				$new_arr[] = $obj;
			}
		}
		$arr = $new_arr;
	} else {
		// Because we popped the last item
		$arr = $orig;
	}

	return serialize( $arr );
}

/**
 * Convert Membership tables charset and collate.
 *
 * Fix to convert membership tables to default charset and collate defined in wp-config.php.
 * Before this version, a bug was not considering charset and collate.
 *
 */
function M_Alterfor18() {
	global $wpdb;

	if ( defined( 'M_LITE' ) ) {
		return;
	}
	$charset_collate = '';
	if ( !empty( $wpdb->charset ) ) {
		$charset_collate = $wpdb->charset;
	}
	if ( !empty( $wpdb->collate ) ) {
		$charset_collate .= " COLLATE {$wpdb->collate}";
	}

	$tables = array(
			membership_db_prefix( $wpdb, 'communications' ),
			membership_db_prefix( $wpdb, 'coupons' ),
			membership_db_prefix( $wpdb, 'levelmeta' ),
			membership_db_prefix( $wpdb, 'membership_levels' ),
			membership_db_prefix( $wpdb, 'member_payments' ),
			membership_db_prefix( $wpdb, 'membership_relationships' ),
			membership_db_prefix( $wpdb, 'membership_rules' ),
			membership_db_prefix( $wpdb, 'subscriptions' ),
			membership_db_prefix( $wpdb, 'subscriptionmeta' ),
			membership_db_prefix( $wpdb, 'subscriptions_levels' ),
			membership_db_prefix( $wpdb, 'subscription_transaction' ),
			membership_db_prefix( $wpdb, 'pings' ),
			membership_db_prefix( $wpdb, 'ping_history' ),
			membership_db_prefix( $wpdb, 'urlgroups' ),
	);
	if( ! empty( $charset_collate ) )
	{
		foreach ( $tables as $table )
		{
			$wpdb->query( "ALTER TABLE $table CONVERT TO CHARACTER SET $charset_collate" );
		}
	}
}
function M_Alterfor16() {
	global $wpdb;

	$column_name = 'order_num';
	$table = 'subscriptions';
	$row = $wpdb->get_results( $wpdb->prepare( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
	WHERE table_name = %s and column_name= %s LIMIT 1", membership_db_prefix( $wpdb, $table ), $column_name ) );

	if(empty($row)){
		$sql = $wpdb->prepare( "ALTER TABLE %s ADD %s INT NOT NULL DEFAULT 0;",
					  membership_db_prefix($wpdb, $table), $column_name );
		$sql = str_replace("'", '`', $sql);
		$wpdb->query($sql);
	}

}

function M_Alterfor15() {
	global $wpdb;

	$column_name = 'sub_id';
	$table = 'communications';
	$row = $wpdb->get_results( $wpdb->prepare( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
	WHERE table_name = %s and column_name= %s LIMIT 1", membership_db_prefix( $wpdb, $table ), $column_name ) );

	if(empty($row)){
		$sql = $wpdb->prepare( "ALTER TABLE %s ADD %s BIGINT NULL DEFAULT NULL  AFTER %s;",
					  membership_db_prefix($wpdb, $table), $column_name, 'id' );
		$sql = str_replace("'", '`', $sql);
		$wpdb->query($sql);
	}

}

function M_Alterfor14() {
	global $wpdb;

	$column_name = 'coupon_apply_to';
	$table = 'coupons';
	$row = $wpdb->get_results( $wpdb->prepare( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
	WHERE table_name = %s and column_name= %s LIMIT 1", membership_db_prefix( $wpdb, $table ), $column_name ) );

	if(empty($row)){
		$sql = $wpdb->prepare( "ALTER TABLE %s ADD %s varchar(20) NULL DEFAULT NULL  AFTER %s;",
					  membership_db_prefix($wpdb, $table), $column_name, 'coupon_used' );
		$sql = str_replace("'", '`', $sql);
		$wpdb->query($sql);
	}

}

function M_Alterfor12() {
	global $wpdb;

	$charset_collate = M_get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'coupons') . "` (
	  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	  `site_id` bigint(20) DEFAULT '0',
	  `couponcode` varchar(250) DEFAULT NULL,
	  `discount` decimal(11,2) DEFAULT '0.00',
	  `discount_type` varchar(5) DEFAULT NULL,
	  `discount_currency` varchar(5) DEFAULT NULL,
	  `coupon_startdate` datetime DEFAULT NULL,
	  `coupon_enddate` datetime DEFAULT NULL,
	  `coupon_sub_id` bigint(20) DEFAULT '0',
	  `coupon_uses` int(11) DEFAULT '0',
	  `coupon_used` int(11) DEFAULT '0',
	  `coupon_apply_to` varchar(20) DEFAULT NULL,
	  PRIMARY KEY (`id`),
	  KEY `couponcode` (`couponcode`)
	) $charset_collate;";

	$wpdb->query( $sql );

}

function M_Alterfor11() {
	global $wpdb;

	$column_name = 'sub_pricetext';
	$table = 'subscriptions';
	$row = $wpdb->get_results( $wpdb->prepare( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
	WHERE table_name = %s and column_name= %s LIMIT 1", membership_db_prefix( $wpdb, $table ), $column_name ) );

	if(empty($row)){
		$sql = $wpdb->prepare( "ALTER TABLE %s ADD %s varchar(200) NULL DEFAULT NULL  AFTER %s;",
					  membership_db_prefix($wpdb, $table), $column_name, 'sub_description' );
		$sql = str_replace("'", '`', $sql);
		$wpdb->query($sql);
	}

}

function M_Alterfor10() {
	global $wpdb;

	$sql = "ALTER TABLE " . membership_db_prefix($wpdb, 'subscriptions_levels') . " CHANGE `level_price` `level_price` decimal(11,2) NULL DEFAULT '0.00';";

	$wpdb->query( $sql );

}

function M_Alterfor6() {
	global $wpdb;

	$charset_collate = M_get_charset_collate();

	$column_name = 'usinggateway';
	$table = 'membership_relationships';
	$row = $wpdb->get_results( $wpdb->prepare( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
	WHERE table_name = %s and column_name= %s LIMIT 1", membership_db_prefix( $wpdb, $table ), $column_name ) );

	if(empty($row)){
		$sql = $wpdb->prepare( "ALTER TABLE %s ADD %s varchar(50) NULL DEFAULT 'admin'  AFTER %s;",
					  membership_db_prefix($wpdb, $table), $column_name, 'order_instance' );
		$sql = str_replace("'", '`', $sql);
		$wpdb->query($sql);
	}

	$sql = "ALTER TABLE " . membership_db_prefix($wpdb, 'membership_relationships') . " ADD INDEX  (`user_id`);";
	$wpdb->query( $sql );

	$sql = "ALTER TABLE " . membership_db_prefix($wpdb, 'membership_relationships') . " ADD INDEX  (`sub_id`);";
	$wpdb->query( $sql );

	$sql = "ALTER TABLE " . membership_db_prefix($wpdb, 'membership_relationships') . " ADD INDEX  (`usinggateway`)";;
	$wpdb->query( $sql );

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'member_payments') . "` (
	  	`id` bigint(11) NOT NULL auto_increment,
		`member_id` bigint(20) default NULL,
		`sub_id` bigint(20) default NULL,
		`level_id` bigint(20) default NULL,
		`level_order` int(11) default NULL,
		`paymentmade` datetime default NULL,
		`paymentexpires` datetime default NULL,
		PRIMARY KEY  (`id`)
	) $charset_collate;";

	$wpdb->query($sql);

}

function M_Alterfor5() {
	global $wpdb;

	$charset_collate = M_get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'pings') . "` (
	  	`id` bigint(20) NOT NULL auto_increment,
		`pingname` varchar(250) default NULL,
		`pinginfo` text,
		`pingtype` varchar(10) default NULL,
		PRIMARY KEY  (`id`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'ping_history') . "` (
	  	`id` bigint(20) NOT NULL auto_increment,
		`ping_id` bigint(20) default NULL,
		`ping_sent` timestamp NULL default NULL,
		`ping_info` text,
		`ping_return` text,
		PRIMARY KEY  (`id`),
		KEY `ping_id` (`ping_id`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'levelmeta') . "` (
	  	`id` bigint(20) NOT NULL auto_increment,
		`level_id` bigint(20) default NULL,
		`meta_key` varchar(250) default NULL,
		`meta_value` text,
		`meta_stamp` timestamp NULL default NULL on update CURRENT_TIMESTAMP,
		PRIMARY KEY  (`id`),
		UNIQUE KEY `level_id` (`level_id`,`meta_key`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'subscriptionmeta') . "` (
	  	`id` bigint(20) NOT NULL auto_increment,
		`sub_id` bigint(20) default NULL,
		`meta_key` varchar(250) default NULL,
		`meta_value` text,
		`meta_stamp` timestamp NULL default NULL on update CURRENT_TIMESTAMP,
		PRIMARY KEY  (`id`),
		UNIQUE KEY `sub_id` (`sub_id`,`meta_key`)
	) $charset_collate;";

	$wpdb->query($sql);
}

function M_Alterfor4() {
	global $wpdb;

	$charset_collate = M_get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'urlgroups') . "` (
	  `id` bigint(20) NOT NULL auto_increment,
	  `groupname` varchar(250) default NULL,
	  `groupurls` text,
	  `isregexp` int(11) default '0',
	  `stripquerystring` int(11) default '0',
	  PRIMARY KEY  (`id`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'communications') . "` (
	  `id` bigint(11) NOT NULL auto_increment,
	  `sub_id` bigint(20) DEFAULT NULL,
	  `subject` varchar(250) default NULL,
	  `message` text,
	  `periodunit` int(11) default NULL,
	  `periodtype` varchar(5) default NULL,
	  `periodprepost` varchar(5) default NULL,
	  `lastupdated` timestamp NULL default NULL on update CURRENT_TIMESTAMP,
	  `active` int(11) default '0',
	  `periodstamp` bigint(20) default '0',
	  PRIMARY KEY  (`id`)
	) $charset_collate;";

	$wpdb->query($sql);
}

function M_Alterfor3() {
	global $wpdb;

	$renameable = array(
		'membership_levels',
		'membership_relationships',
		'membership_rules',
		'subscriptions',
		'subscriptions_levels',
		'subscription_transaction',
	);

	foreach( $renameable as $table ) {

		$row = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM INFORMATION_SCHEMA.COLUMNS
		WHERE table_name = %s and column_name='id' LIMIT 1", membership_db_prefix( $wpdb, $table, false ) ) );

		if ( ! empty( $row ) ) {
			$sql = $wpdb->prepare( "RENAME TABLE %s TO %s;",
			membership_db_prefix($wpdb, $table, false), membership_db_prefix($wpdb, $table ) );
			$sql = str_replace("'", '`', $sql);

			$wpdb->query($sql);
		}
	}

}

function M_Alterfor2() {
	global $wpdb;

	$column_name = 'level_period_unit';
	$table = 'subscriptions_levels';
	$row = $wpdb->get_results( $wpdb->prepare( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
	WHERE table_name = %s and column_name= %s LIMIT 1", membership_db_prefix( $wpdb, $table ), $column_name ) );

	if(empty($row)){
		$sql = $wpdb->prepare( "ALTER TABLE %s ADD %s varchar(1) NULL DEFAULT 'd'  AFTER %s;",
					  membership_db_prefix($wpdb, $table), $column_name, 'level_order' );
		$sql = str_replace("'", '`', $sql);
		$wpdb->query($sql);
	}

}

function M_Createtables() {

	global $wpdb;

	$charset_collate = M_get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'membership_levels') . "` (
	  `id` bigint(20) NOT NULL auto_increment,
	  `level_title` varchar(250) default NULL,
	  `level_slug` varchar(250) default NULL,
	  `level_active` int(11) default '0',
	  `level_count` bigint(20) default '0',
	  PRIMARY KEY  (`id`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'membership_relationships') . "` (
	  	`rel_id` bigint(20) NOT NULL auto_increment,
		`user_id` bigint(20) default '0',
		`sub_id` bigint(20) default '0',
		`level_id` bigint(20) default '0',
		`startdate` datetime default NULL,
		`updateddate` datetime default NULL,
		`expirydate` datetime default NULL,
		`order_instance` bigint(20) default '0',
		`usinggateway` varchar(50) default 'admin',
		PRIMARY KEY  (`rel_id`),
		KEY `user_id` (`user_id`),
		KEY `sub_id` (`sub_id`),
		KEY `usinggateway` (`usinggateway`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'membership_rules') . "` (
	  `level_id` bigint(20) NOT NULL default '0',
	  `rule_ive` varchar(20) NOT NULL default '',
	  `rule_area` varchar(20) NOT NULL default '',
	  `rule_value` text,
	  `rule_order` int(11) default '0',
	  PRIMARY KEY  (`level_id`,`rule_ive`,`rule_area`),
	  KEY `rule_area` (`rule_area`),
	  KEY `rule_ive` (`rule_ive`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'subscriptions') . "` (
	  `id` bigint(20) NOT NULL auto_increment,
	  `sub_name` varchar(200) default NULL,
	  `sub_active` int(11) default '0',
	  `sub_public` int(11) default '0',
	  `sub_count` bigint(20) default '0',
	  `sub_description` text,
	  `sub_pricetext` varchar(200) DEFAULT NULL,
	  PRIMARY KEY  (`id`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'subscriptions_levels') . "` (
	  	`sub_id` bigint(20) default NULL,
		`level_id` bigint(20) default NULL,
		`level_period` int(11) default NULL,
		`sub_type` varchar(20) default NULL,
		`level_price` decimal(11,2) default '0.00',
		`level_currency` varchar(5) default NULL,
		`level_order` bigint(20) default '0',
		`level_period_unit` varchar(1) default 'd',
		KEY `sub_id` (`sub_id`),
		KEY `level_id` (`level_id`)
		) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'subscription_transaction') . "` (
	  `transaction_ID` bigint(20) unsigned NOT NULL auto_increment,
	  `transaction_subscription_ID` bigint(20) NOT NULL default '0',
	  `transaction_user_ID` bigint(20) NOT NULL default '0',
	  `transaction_sub_ID` bigint(20) default '0',
	  `transaction_paypal_ID` varchar(30) default NULL,
	  `transaction_payment_type` varchar(20) default NULL,
	  `transaction_stamp` bigint(35) NOT NULL default '0',
	  `transaction_total_amount` bigint(20) default NULL,
	  `transaction_currency` varchar(35) default NULL,
	  `transaction_status` varchar(35) default NULL,
	  `transaction_duedate` date default NULL,
	  `transaction_gateway` varchar(50) default NULL,
	  `transaction_note` text,
	  `transaction_expires` datetime default NULL,
	  PRIMARY KEY  (`transaction_ID`),
	  KEY `transaction_gateway` (`transaction_gateway`),
	  KEY `transaction_subscription_ID` (`transaction_subscription_ID`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'urlgroups') . "` (
	  `id` bigint(20) NOT NULL auto_increment,
	  `groupname` varchar(250) default NULL,
	  `groupurls` text,
	  `isregexp` int(11) default '0',
	  `stripquerystring` int(11) default '0',
	  PRIMARY KEY  (`id`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'communications') . "` (
	  `id` bigint(11) NOT NULL auto_increment,
	  `sub_id` bigint(20) DEFAULT NULL,
	  `subject` varchar(250) default NULL,
	  `message` text,
	  `periodunit` int(11) default NULL,
	  `periodtype` varchar(5) default NULL,
	  `periodprepost` varchar(5) default NULL,
	  `lastupdated` timestamp NULL default NULL on update CURRENT_TIMESTAMP,
	  `active` int(11) default '0',
	  `periodstamp` bigint(20) default '0',
	  PRIMARY KEY  (`id`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'pings') . "` (
	  	`id` bigint(20) NOT NULL auto_increment,
		`pingname` varchar(250) default NULL,
		`pingurl` varchar(250) default NULL,
		`pinginfo` text,
		`pingtype` varchar(10) default NULL,
		PRIMARY KEY  (`id`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'ping_history') . "` (
	  	`id` bigint(20) NOT NULL auto_increment,
		`ping_id` bigint(20) default NULL,
		`ping_sent` timestamp NULL default NULL,
		`ping_info` text,
		`ping_return` text,
		PRIMARY KEY  (`id`),
		KEY `ping_id` (`ping_id`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'levelmeta') . "` (
	  	`id` bigint(20) NOT NULL auto_increment,
		`level_id` bigint(20) default NULL,
		`meta_key` varchar(250) default NULL,
		`meta_value` text,
		`meta_stamp` timestamp NULL default NULL on update CURRENT_TIMESTAMP,
		PRIMARY KEY  (`id`),
		UNIQUE KEY `level_id` (`level_id`,`meta_key`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'subscriptionmeta') . "` (
	  	`id` bigint(20) NOT NULL auto_increment,
		`sub_id` bigint(20) default NULL,
		`meta_key` varchar(250) default NULL,
		`meta_value` text,
		`meta_stamp` timestamp NULL default NULL on update CURRENT_TIMESTAMP,
		PRIMARY KEY  (`id`),
		UNIQUE KEY `sub_id` (`sub_id`,`meta_key`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'member_payments') . "` (
	  	`id` bigint(11) NOT NULL auto_increment,
		`member_id` bigint(20) default NULL,
		`sub_id` bigint(20) default NULL,
		`level_id` bigint(20) default NULL,
		`level_order` int(11) default NULL,
		`paymentmade` datetime default NULL,
		`paymentexpires` datetime default NULL,
		PRIMARY KEY  (`id`)
	) $charset_collate;";

	$wpdb->query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'coupons') . "` (
	  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	  `site_id` bigint(20) DEFAULT '0',
	  `couponcode` varchar(250) DEFAULT NULL,
	  `discount` decimal(11,2) DEFAULT '0.00',
	  `discount_type` varchar(5) DEFAULT NULL,
	  `discount_currency` varchar(5) DEFAULT NULL,
	  `coupon_startdate` datetime DEFAULT NULL,
	  `coupon_enddate` datetime DEFAULT NULL,
	  `coupon_sub_id` bigint(20) DEFAULT '0',
	  `coupon_uses` int(11) DEFAULT '0',
	  `coupon_used` int(11) DEFAULT '0',
	  `coupon_apply_to` varchar(20) DEFAULT NULL,
	  PRIMARY KEY (`id`),
	  KEY `couponcode` (`couponcode`)
	) $charset_collate;";

	$wpdb->query( $sql );

	do_action( 'membership_create_new_tables' );
}

function M_Create_single_table( $name ) {

	global $wpdb;

	$charset_collate = M_get_charset_collate();

	switch( $name ) {

		case membership_db_prefix($wpdb, 'membership_levels'):
					$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'membership_levels') . "` (
					  `id` bigint(20) NOT NULL auto_increment,
					  `level_title` varchar(250) default NULL,
					  `level_slug` varchar(250) default NULL,
					  `level_active` int(11) default '0',
					  `level_count` bigint(20) default '0',
					  PRIMARY KEY  (`id`)
					) $charset_collate;";
					break;

		case membership_db_prefix($wpdb, 'membership_relationships'):
					$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'membership_relationships') . "` (
					  	`rel_id` bigint(20) NOT NULL auto_increment,
						`user_id` bigint(20) default '0',
						`sub_id` bigint(20) default '0',
						`level_id` bigint(20) default '0',
						`startdate` datetime default NULL,
						`updateddate` datetime default NULL,
						`expirydate` datetime default NULL,
						`order_instance` bigint(20) default '0',
						`usinggateway` varchar(50) default 'admin',
						PRIMARY KEY  (`rel_id`),
						KEY `user_id` (`user_id`),
						KEY `sub_id` (`sub_id`),
						KEY `usinggateway` (`usinggateway`)
					) $charset_collate;";
					break;

		case membership_db_prefix($wpdb, 'membership_rules'):
					$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'membership_rules') . "` (
					  `level_id` bigint(20) NOT NULL default '0',
					  `rule_ive` varchar(20) NOT NULL default '',
					  `rule_area` varchar(20) NOT NULL default '',
					  `rule_value` text,
					  `rule_order` int(11) default '0',
					  PRIMARY KEY  (`level_id`,`rule_ive`,`rule_area`),
					  KEY `rule_area` (`rule_area`),
					  KEY `rule_ive` (`rule_ive`)
					) $charset_collate;";
					break;

		case membership_db_prefix($wpdb, 'subscriptions'):
					$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'subscriptions') . "` (
					  `id` bigint(20) NOT NULL auto_increment,
					  `sub_name` varchar(200) default NULL,
					  `sub_active` int(11) default '0',
					  `sub_public` int(11) default '0',
					  `sub_count` bigint(20) default '0',
					  `sub_description` text,
					  PRIMARY KEY  (`id`)
					) $charset_collate;";
					break;

		case membership_db_prefix($wpdb, 'subscriptions_levels'):
					$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'subscriptions_levels') . "` (
					  	`sub_id` bigint(20) default NULL,
						`level_id` bigint(20) default NULL,
						`level_period` int(11) default NULL,
						`sub_type` varchar(20) default NULL,
						`level_price` decimal(11,2) default '0.00',
						`level_currency` varchar(5) default NULL,
						`level_order` bigint(20) default '0',
						`level_period_unit` varchar(1) default 'd',
						KEY `sub_id` (`sub_id`),
						KEY `level_id` (`level_id`)
						) $charset_collate;";
					break;

		case membership_db_prefix($wpdb, 'subscription_transaction'):
					$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'subscription_transaction') . "` (
					  `transaction_ID` bigint(20) unsigned NOT NULL auto_increment,
					  `transaction_subscription_ID` bigint(20) NOT NULL default '0',
					  `transaction_user_ID` bigint(20) NOT NULL default '0',
					  `transaction_sub_ID` bigint(20) default '0',
					  `transaction_paypal_ID` varchar(30) default NULL,
					  `transaction_payment_type` varchar(20) default NULL,
					  `transaction_stamp` bigint(35) NOT NULL default '0',
					  `transaction_total_amount` bigint(20) default NULL,
					  `transaction_currency` varchar(35) default NULL,
					  `transaction_status` varchar(35) default NULL,
					  `transaction_duedate` date default NULL,
					  `transaction_gateway` varchar(50) default NULL,
					  `transaction_note` text,
					  `transaction_expires` datetime default NULL,
					  PRIMARY KEY  (`transaction_ID`),
					  KEY `transaction_gateway` (`transaction_gateway`),
					  KEY `transaction_subscription_ID` (`transaction_subscription_ID`)
					) $charset_collate;";
					break;

		case membership_db_prefix($wpdb, 'urlgroups'):
					$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'urlgroups') . "` (
					  `id` bigint(20) NOT NULL auto_increment,
					  `groupname` varchar(250) default NULL,
					  `groupurls` text,
					  `isregexp` int(11) default '0',
					  `stripquerystring` int(11) default '0',
					  PRIMARY KEY  (`id`)
					) $charset_collate;";
					break;

		case membership_db_prefix($wpdb, 'communications'):
					$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'communications') . "` (
					  `id` bigint(11) NOT NULL auto_increment,
					  `sub_id` bigint(20) DEFAULT NULL,
					  `subject` varchar(250) default NULL,
					  `message` text,
					  `periodunit` int(11) default NULL,
					  `periodtype` varchar(5) default NULL,
					  `periodprepost` varchar(5) default NULL,
					  `lastupdated` timestamp NULL default NULL on update CURRENT_TIMESTAMP,
					  `active` int(11) default '0',
					  `periodstamp` bigint(20) default '0',
					  PRIMARY KEY  (`id`)
					) $charset_collate;";
					break;

		case membership_db_prefix($wpdb, 'pings'):
					$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'pings') . "` (
					  	`id` bigint(20) NOT NULL auto_increment,
						`pingname` varchar(250) default NULL,
						`pingurl` varchar(250) default NULL,
						`pinginfo` text,
						`pingtype` varchar(10) default NULL,
						PRIMARY KEY  (`id`)
					) $charset_collate;";
					break;

		case membership_db_prefix($wpdb, 'ping_history'):
					$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'ping_history') . "` (
					  	`id` bigint(20) NOT NULL auto_increment,
						`ping_id` bigint(20) default NULL,
						`ping_sent` timestamp NULL default NULL,
						`ping_info` text,
						`ping_return` text,
						PRIMARY KEY  (`id`),
						KEY `ping_id` (`ping_id`)
					) $charset_collate;";
					break;

		case membership_db_prefix($wpdb, 'levelmeta'):
					$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'levelmeta') . "` (
					  	`id` bigint(20) NOT NULL auto_increment,
						`level_id` bigint(20) default NULL,
						`meta_key` varchar(250) default NULL,
						`meta_value` text,
						`meta_stamp` timestamp NULL default NULL on update CURRENT_TIMESTAMP,
						PRIMARY KEY  (`id`),
						UNIQUE KEY `level_id` (`level_id`,`meta_key`)
					) $charset_collate;";
					break;

		case membership_db_prefix($wpdb, 'subscriptionmeta'):
					$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'subscriptionmeta') . "` (
					  	`id` bigint(20) NOT NULL auto_increment,
						`sub_id` bigint(20) default NULL,
						`meta_key` varchar(250) default NULL,
						`meta_value` text,
						`meta_stamp` timestamp NULL default NULL on update CURRENT_TIMESTAMP,
						PRIMARY KEY  (`id`),
						UNIQUE KEY `sub_id` (`sub_id`,`meta_key`)
					) $charset_collate;";
					break;

		case membership_db_prefix($wpdb, 'member_payments'):
					$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'member_payments') . "` (
					  	`id` bigint(11) NOT NULL auto_increment,
						`member_id` bigint(20) default NULL,
						`sub_id` bigint(20) default NULL,
						`level_id` bigint(20) default NULL,
						`level_order` int(11) default NULL,
						`paymentmade` datetime default NULL,
						`paymentexpires` datetime default NULL,
						PRIMARY KEY  (`id`)
					) $charset_collate;";
					break;

		case membership_db_prefix($wpdb, 'coupons'):
					$sql = "CREATE TABLE IF NOT EXISTS `" . membership_db_prefix($wpdb, 'coupons') . "` (
					  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					  `site_id` bigint(20) DEFAULT '0',
					  `couponcode` varchar(250) DEFAULT NULL,
					  `discount` decimal(11,2) DEFAULT '0.00',
					  `discount_type` varchar(5) DEFAULT NULL,
					  `discount_currency` varchar(5) DEFAULT NULL,
					  `coupon_startdate` datetime DEFAULT NULL,
					  `coupon_enddate` datetime DEFAULT NULL,
					  `coupon_sub_id` bigint(20) DEFAULT '0',
					  `coupon_uses` int(11) DEFAULT '0',
					  `coupon_used` int(11) DEFAULT '0',
					  `coupon_apply_to` varchar(20) DEFAULT NULL,
					  PRIMARY KEY (`id`),
					  KEY `couponcode` (`couponcode`)
					) $charset_collate;";
					break;

	}

	$wpdb->query($sql);

}

function M_add_possible_missing_fields( $table, $name, $type, $after, $key = false ) {
	global $wpdb;

	switch ( $name ) {
		case 'usinggateway':
			$defaults = $name . " " . $type . " default 'admin' AFTER " . $after;
			$sql = "ALTER TABLE " . $table . " ADD COLUMN " . $defaults;
			$wpdb->query( $sql );
			// Add the key
			$sql = "ALTER TABLE " . $table . " ADD INDEX  (`" . $name . "`)";

			$wpdb->query( $sql );
			break;

		default:
			$defaults = $name . " " . $type . " AFTER " . $after;
			$sql = "ALTER TABLE " . $table . " ADD COLUMN " . $defaults;
			$wpdb->query( $sql );
			if ( $key ) {
				$sql = "ALTER TABLE " . $table . " ADD INDEX  (`" . $name . "`)";
				$wpdb->query( $sql );
			}
			break;
	}
}

function M_repair_field_type ( $table, $name, $type ) {
	global $wpdb;

	$sql = "ALTER TABLE $table MODIFY $name $type";
	$wpdb->query( $sql );

}

function M_verify_tables() {

	global $wpdb;

	$tables = M_build_database_structure();

	foreach( $tables as $name => $fields ) {

		echo "<p>" . __('Checking table : ', 'membership') . $name . " - ";

		$sql = "SHOW TABLES LIKE '{$name}';";
		$t = $wpdb->get_var( $sql );

		if($t == $name) {
			echo "<span style='color: green;'>" . __('Ok', 'membership') . "</span>";
			echo "</p>";

			echo "<p>" . __('Checking fields in table : ', 'membership') . $name . " - ";

			$sql = "SHOW COLUMNS FROM {$name};";
			$t = $wpdb->get_results( $sql );

			foreach( $fields as $fieldname => $type ) {
				$found = false;
				echo "<br/>" . $fieldname . " - ";
				foreach($t as $dbf) {
					if($dbf->Field == $fieldname && $dbf->Type == $type) {
						$found = true;
						break;
					}
				}
				if($found) {
					echo "<span style='color: green;'>" . __('Ok', 'membership') . "</span>";
				} else {
					echo "<span style='color: red;'>" . __('Missing or Incorrect', 'membership') . "</span>";
				}
			}

			echo "</p>";

		} else {
			echo "<span style='color: red;'>" . __('Missing', 'membership') . "</span>";
			echo "</p>";
		}

	}

}

function M_repair_tables( $print = true ) {
	global $wpdb;

	$tables = M_build_database_structure();
	$html = '';
	foreach( $tables as $name => $fields ) {

		$html .= "<p>" . __('Checking table : ', 'membership') . $name . " - ";

		$sql = "SHOW TABLES LIKE '{$name}';";
		$t = $wpdb->get_var( $sql );

		if($t == $name) {
			$html .= "<span style='color: green;'>" . __('Ok', 'membership') . "</span>";
			$html .= "</p>";

			$html .= "<p>" . __('Checking fields in table : ', 'membership') . $name . " - ";

			$sql = "SHOW COLUMNS FROM {$name};";
			$t = $wpdb->get_results( $sql );

			$pfield = '';
			foreach( $fields as $fieldname => $type ) {
				$found = false;
				$incorrect_type = false;
				$html .= "<br/>" . $fieldname . " - ";
				foreach($t as $dbf) {
					//print_r($dbf);
					if($dbf->Field == $fieldname && $dbf->Type == $type) {
						$found = true;
						break;
					}
					//Column Field found, but with incorrect data type
					if($dbf->Field == $fieldname && $dbf->Type != $type) {
						$incorrect_type = true;
						break;
					}
				}
				if($found) {
					$html .= "<span style='color: green;'>" . __('Ok', 'membership') . "</span>";
				} else {
					if( $incorrect_type ) {
						M_repair_field_type( $name, $fieldname, $type );
					} else {
						M_add_possible_missing_fields( $name, $fieldname, $type, $pfield );
					}

					$html .= "<span style='color: red;'>" . __('Fixed', 'membership') . "</span>";
				}
				$pfield = $fieldname;
			}

			$html .= "</p>";

		} else {

			M_Create_single_table( $name );

			$html .= "<span style='color: red;'>" . __('Fixed', 'membership') . "</span>";
			$html .= "</p>";
		}

	}

	// $html .= "<p>" . __('Cleaning up empty subscription/level relationships : ', 'membership');
	// $wpdb->delete( MEMBERSHIP_TABLE_RELATIONS, array( 'sub_id' => 0 ), array( '%d' ) );
	// $wpdb->delete( MEMBERSHIP_TABLE_RELATIONS, array( 'level_id' => 0 ), array( '%d' ) );
	// $html .= "<span style='color: green;'>" . __('Ok', 'membership') . "</span></p>";

	if( $print ) {
		echo $html;
	}
}

function M_build_database_structure() {
	global $wpdb;

	$bi = 'bigint(20)';
	$biu = 'bigint(20) unsigned';
	$bi11 = 'bigint(11)';
	$bi35 = 'bigint(35)';
	$i = 'int(11)';
	$v1 = 'varchar(1)';
	$v5 = 'varchar(5)';
	$v10 = 'varchar(10)';
	$v30 = 'varchar(30)';
	$v35 = 'varchar(35)';
	$v50 = 'varchar(50)';
	$v20 = 'varchar(20)';
	$v200 = 'varchar(200)';
	$v250 = 'varchar(250)';
	$t = 'text';
	$jd = 'date';
	$d = 'datetime';
	$ts = 'timestamp';
	$dc = 'decimal(11,2)';

	$structure = array(
		membership_db_prefix( $wpdb, 'membership_levels' ) => array(
			'id'           => $bi,
			'level_title'  => $v250,
			'level_slug'   => $v250,
			'level_active' => $i,
			'level_count'  => $bi,
		),

		membership_db_prefix( $wpdb, 'membership_relationships' ) => array(
			'rel_id'         => $bi,
			'user_id'        => $bi,
			'sub_id'         => $bi,
			'level_id'       => $bi,
			'startdate'      => $d,
			'updateddate'    => $d,
			'expirydate'     => $d,
			'order_instance' => $bi,
			'usinggateway'   => $v50,
		),

		membership_db_prefix( $wpdb, 'membership_rules' ) => array(
			'level_id'   => $bi,
			'rule_ive'   => $v20,
			'rule_area'  => $v20,
			'rule_value' => $t,
			'rule_order' => $i,
		),

		membership_db_prefix( $wpdb, 'subscriptions' ) => array(
			'id'              => $bi,
			'sub_name'        => $v200,
			'sub_active'      => $i,
			'sub_public'      => $i,
			'sub_count'       => $bi,
			'sub_description' => $t,
			'sub_pricetext'   => $v200,
			'order_num'       => $i,
		),

		membership_db_prefix( $wpdb, 'subscriptions_levels' ) => array(
			'sub_id'            => $bi,
			'level_id'          => $bi,
			'level_period'      => $i,
			'sub_type'          => $v20,
			'level_price'       => $dc,
			'level_currency'    => $v5,
			'level_order'       => $bi,
			'level_period_unit' => $v1,
		),

		membership_db_prefix( $wpdb, 'subscription_transaction' ) => array(
			'transaction_ID'              => $biu,
			'transaction_subscription_ID' => $bi,
			'transaction_user_ID'         => $bi,
			'transaction_sub_ID'          => $bi,
			'transaction_paypal_ID'       => $v30,
			'transaction_payment_type'    => $v20,
			'transaction_stamp'           => $bi35,
			'transaction_total_amount'    => $bi,
			'transaction_currency'        => $v35,
			'transaction_duedate'         => $jd,
			'transaction_gateway'         => $v50,
			'transaction_note'            => $t,
			'transaction_expires'         => $d,
		),

		membership_db_prefix( $wpdb, 'urlgroups' ) => array(
			'id'               => $bi,
			'groupname'        => $v250,
			'groupurls'        => $t,
			'isregexp'         => $i,
			'stripquerystring' => $i,
		),

		membership_db_prefix( $wpdb, 'communications' ) => array(
			'id'            => $bi11,
			'sub_id'        => $bi,
			'subject'       => $v250,
			'message'       => $t,
			'periodunit'    => $i,
			'periodtype'    => $v5,
			'periodprepost' => $v5,
			'lastupdated'   => $ts,
			'active'        => $i,
			'periodstamp'   => $bi,
		),

		membership_db_prefix( $wpdb, 'pings' ) => array(
			'id'       => $bi,
			'pingname' => $v250,
			'pingurl'  => $v250,
			'pinginfo' => $t,
			'pingtype' => $v10,
		),

		membership_db_prefix( $wpdb, 'ping_history' ) => array(
			'id'          => $bi,
			'ping_id'     => $bi,
			'ping_sent'   => $ts,
			'ping_info'   => $t,
			'ping_return' => $t,
		),

		membership_db_prefix( $wpdb, 'levelmeta' ) => array(
			'id'         => $bi,
			'level_id'   => $bi,
			'meta_key'   => $v250,
			'meta_value' => $t,
			'meta_stamp' => $ts,
		),

		membership_db_prefix( $wpdb, 'subscriptionmeta' ) => array(
			'id'         => $bi,
			'sub_id'     => $bi,
			'meta_key'   => $v250,
			'meta_value' => $t,
			'meta_stamp' => $ts,
		),

		membership_db_prefix( $wpdb, 'member_payments' ) => array(
			'id'             => $bi11,
			'member_id'      => $bi,
			'sub_id'         => $bi,
			'level_id'       => $bi,
			'level_order'    => $i,
			'paymentmade'    => $d,
			'paymentexpires' => $d,
		),

		membership_db_prefix( $wpdb, 'coupons' ) => array(
			'id'                => $biu,
			'site_id'           => $bi,
			'couponcode'        => $v250,
			'discount'          => $dc,
			'discount_type'     => $v5,
			'discount_currency' => $v5,
			'coupon_startdate'  => $d,
			'coupon_enddate'    => $d,
			'coupon_sub_id'     => $bi,
			'coupon_uses'       => $i,
			'coupon_used'       => $i,
			'coupon_apply_to'   => $v20,
		)
	);

	return $structure;
}