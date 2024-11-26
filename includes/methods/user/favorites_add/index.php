<?php
$required_setting = ENABLE_FAVORITES;

$id = $vars["id"];
$favorites = new Favorites();
$favorites->add($id);
$GLOBALS["favorites"] = $favorites;
$favorite_count = get_post_meta($id, "wpcf_favorites_count", true);
$button_text = trans("Remove");
$feedback_text = "";
if (!empty($favorite_count)) {
	$feedback_text =
		"<span>" .
			sprintf(
				trans("%s person's favorite tour."),
				$favorite_count
			) .
		"</span>";
}
$html = $button_text . $feedback_text;
$data = [
	"error" => false,
	"message" =>
		"<b class='d-block'>" .
			get_the_title($id) .
		"</b> added to your favorites.",
	"data" => $favorites->favorites,
	"html" => $html,
	"count" => $favorites->count()
];
echo json_encode($data);
die();