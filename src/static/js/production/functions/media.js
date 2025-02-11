
jQuery.fn.allLazyLoaded = function(fn){
    if(this.length){
        var loadingClass, toLoadClass;
        var $ = jQuery;
        var isConfigured = function(){
            var hasLazySizes = !!window.lazySizes;

            if(!loadingClass && hasLazySizes){
                loadingClass = '.' + lazySizes.cfg.loadingClass;
                toLoadClass = '.' + lazySizes.cfg.lazyClass;
            }

            return hasLazySizes;
        };

        var isComplete = function(){
            return !('complete' in this) || this.complete;
        };

        this.each(function(){
            var container = this;
            var testLoad = function(){

                if(isConfigured() && !$(toLoadClass, container).length && !$(loadingClass, container).not(isComplete).length){
                    container.removeEventListener('load', rAFedTestLoad, true);
                    if(fn){
                        fn.call(container, container);
                    }
                    $(container).trigger('containerlazyloaded');
                }
            };
            var rAFedTestLoad = function(){
                requestAnimationFrame(testLoad);
            };

            container.addEventListener('load', rAFedTestLoad, true);
            rAFedTestLoad();
        });
    }
    return this;
};