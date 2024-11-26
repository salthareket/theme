<?php

function fix_turkish_plurals( array $data, Loco_Locale $locale ){
	if( 'tr' === $locale->lang ){
		$data[0] = 'n > 1';
		$data[1] = array('one','other');
	}
	return $data;
}
add_filter( 'loco_locale_plurals', 'fix_turkish_plurals', 10, 2 );
