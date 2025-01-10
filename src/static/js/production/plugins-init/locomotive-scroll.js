function init_locomotive_scroll(){
	const locomotiveScroll = new LocomotiveScroll();
	window.addEventListener("progressEvent", (e) => {
		let { progress, target } = e.detail;
		const property = target.getAttribute("data-scroll-event-property");
		target = ["scale", "rotate", "translateX", "translateY", "skew"].includes(property)
      	? target.firstElementChild || target // İlk child yoksa, target kullan
      	: target;

		if (property) {
		    // Hangi CSS özelliğinin değiştirileceğine karar ver
		    switch (property) {
		      case "opacity":
		        target.style.opacity = 1 - progress;
		        break;

		      case "scale":
		        target.style.transform = `scale(${1 + progress})`;
		        break;

		      case "rotate":
		        target.style.transform = `rotate(${progress * 360}deg)`;
		        break;

		      case "translateX":
		        target.style.transform = `translateX(${progress * 100}%)`;
		        break;

		      case "translateY":
		        target.style.transform = `translateY(${progress * 100}%)`;
		        break;

		      case "skew":
		        target.style.transform = `skew(${progress * 20}deg)`;
		        break;

		      case "blur":
		        target.style.filter = `blur(${progress * 5}px)`;
		        break;

		      case "brightness":
		        target.style.filter = `brightness(${1 + progress})`;
		        break;

		      case "grayscale":
		        target.style.filter = `grayscale(${progress})`;
		        break;

		      case "invert":
		        target.style.filter = `invert(${progress})`;
		        break;

		      case "border-radius":
		        target.style.borderRadius = `${progress * 50}%`;
		        break;

		      case "clippath-circle":
		        target.style.clipPath = `circle(${progress * 50}% at center)`;
		        break;

		      case "clippath-ellipse":
		        target.style.clipPath = `ellipse(${progress * 50}% ${progress * 30}% at center)`;
		        break;

		      case "clippath-polygon":
		        target.style.clipPath = `polygon(50% 0%, 100% ${progress * 100}%, 0% ${progress * 100}%)`;
		        break;

		      case "clippath-star":
		        target.style.clipPath = `polygon(
		          50% 0%, 
		          61% ${progress * 30}%, 
		          98% ${progress * 30}%, 
		          68% ${progress * 60}%, 
		          79% 100%, 
		          50% ${progress * 80}%, 
		          21% 100%, 
		          32% ${progress * 60}%, 
		          2% ${progress * 30}%, 
		          39% ${progress * 30}%
		        )`;
		        break;

		      case "background-color":
		        target.style.backgroundColor = `rgba(${progress * 255}, ${progress * 100}, ${progress * 50}, 1)`;
		        break;

		      case "font-size":
		        target.style.fontSize = `${progress * 3 + 1}rem`;
		        break;

		      case "letter-spacing":
		        target.style.letterSpacing = `${progress * 5}px`;
		        break;

		      case "padding":
		        target.style.padding = `${progress * 20}px`;
		        break;

		      default:
		        console.warn(`Unknown property: ${property}`);
		    }
		} else {
		    console.warn("No 'data-scroll-property' defined on target");
		}
	});
}