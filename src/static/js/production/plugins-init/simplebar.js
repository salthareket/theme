function init_simplebar(){
	Array.prototype.forEach.call(
	  document.querySelectorAll('.simplebar'),
	  (el) => new SimpleBar(el)
	);
}