/**
 * Douaa slider class
 * @type {Object}
 */
var douaaSlider = {
    oneDouaaShowingTime: 20000,
    /**
     * it saves html (ul,li)
     * @type String
     */
    sliderHtmlContent: '',
    /**
     *  init douaa after prayer slider
     */
    init: function () {
        // if mobile device ignore douaa slider
        if (isMobile) {
            return;
        }

        if (lang === "ar") {
            $(".adhkar-after-prayer .fr").remove();
        }

        var screenWidth = $(window).width();
        $('.adhkar-after-prayer li').width(screenWidth);
        var slideCount = $('.adhkar-after-prayer li').length;
        var sliderUlWidth = slideCount * screenWidth;
        $('.adhkar-after-prayer ul').css({width: sliderUlWidth});
        //save html slider
        this.sliderHtmlContent = $('.adhkar-after-prayer').html();
    },
    /**
     * If enabled show douaa after prayer for 5 minutes
     * @param {Number} currentTimeIndex
     */
    show: function (currentTimeIndex) {
        // if jumua and mosque type
        if (prayer.isJumua(currentTimeIndex) && prayer.isMosque) {
            return;
        }

        if (prayer.confData.duaAfterPrayerEnabled) {

            $("#black-screen, .main").fadeOut(500, function () {
                $(".adhkar-after-prayer").fadeIn(500, function () {
                   douaaSlider.setFontSize();
                });
            });

            var douaaInterval = setInterval(function () {
                douaaSlider.moveRight();
                // if (lang === "ar") {
                //     douaaSlider.moveRight();
                // }
                //
                // if (lang !== "ar") {
                //     douaaSlider.moveLeft();
                // }
            }, douaaSlider.oneDouaaShowingTime);

            setTimeout(function () {
                clearInterval(douaaInterval);
                $(".adhkar-after-prayer").fadeOut(500, function () {
                    $(".main").fadeIn(500);
                });

                // show messages if exist after 10 sec after duaa
                setTimeout(function () {
                    messageInfoSlider.get();
                }, 10 * prayer.oneSecond);

            }, douaaSlider.getTimeForShow());
        } else {
            $("#black-screen").fadeOut(500, function () {
                $(".main").fadeIn(500);
            });
            setTimeout(function () {
                // no douaa, show messages if exist after 2 min after prayer
                messageInfoSlider.get();
            }, 2 * prayer.oneMinute);
        }
    },
    /**
     * Number of seconds to show all douaa
     * @returns {Number}
     */
    getTimeForShow: function () {
        return ($('.adhkar-after-prayer li').length * douaaSlider.oneDouaaShowingTime);
    },
    moveRight: function () {
        var screenWidth = $(window).width();
        $('.adhkar-after-prayer ul').animate({
            left: -screenWidth
        }, 1000, function () {
            $('.adhkar-after-prayer li:first-child').appendTo('.adhkar-after-prayer ul');
            $('.adhkar-after-prayer ul').css('left', '');
        });
    },
    moveLeft: function () {
        var screenWidth = $(window).width();
        $('.adhkar-after-prayer ul').animate({
            left: +screenWidth
        }, 1000, function () {
            $('.adhkar-after-prayer li:last-child').prependTo('.adhkar-after-prayer ul');
            $('.adhkar-after-prayer ul').css('left', '');
        });
    },
    setFontSize: function () {
        $('.slider li').each(function (i, slide) {
            fixFontSize(slide, 180);
        });
    }
};

/**
 * Messages slider class
 * @type {Object}
 */
var messageInfoSlider = {
    messageInfoIsShowing: false,
    /**
     * it saves html (ul,li)
     * @type String
     */
    sliderHtmlContent: '',
    /**
     * Cron handling message info showing
     * The messages will be shown
     *  - 5 before every adhan
     *  - 5 before jumu`a
     *  - At defined time
     */
    initCronMessageInfo: function () {
        setInterval(function () {
            if (prayer.isPrayingMoment()) {
                return false;
            }

            if (messageInfoSlider.messageInfoIsShowing === false) {
                messageInfoSlider.get();
            }
        }, prayer.oneMinute * 9);
    },
    /**
     *  run message slider
     */
    run: function () {
        var screenWidth = $(window).width();
        var nbSlides = $('.message-slider li').length;

        $('.message-slider li').width(screenWidth);

        var sliderUlWidth = nbSlides * screenWidth;
        $('.message-slider ul').css({width: sliderUlWidth});

        //save html slider
        messageInfoSlider.sliderHtmlContent = $('.message-slider .messageContent').html();

        $(".main").fadeOut(500, function () {
            $(".message-slider").fadeIn(500);
            messageInfoSlider.setFontSize();
        });

        messageInfoSlider.messageInfoIsShowing = true;

        var interval = setInterval(function () {
            messageInfoSlider.moveRight();
        }, prayer.confData.timeToDisplayMessage * 1000);

        setTimeout(function () {
            clearInterval(interval);
            $(".message-slider").fadeOut(1000, function () {
                $(".main").fadeIn(1000);
            });
            messageInfoSlider.messageInfoIsShowing = false;
        }, (nbSlides * prayer.confData.timeToDisplayMessage * 1000) - 1000);
    },
    /**
     * Get message from server
     */
    get: function () {
        if ($(".main").is(":visible")) {
            $.ajax({
                dataType: "json",
                url: $(".message-slider").data("remote"),
                success: function (data) {
                    if (data.messages.length > 0) {
                        var slide;
                        var items = [];
                        $.each(data.messages, function (i, message) {
                            slide = '<li>';
                            if (message.image) {
                                slide += '<img src="/upload/' + message.image + '"/>';
                            } else {
                                if (message.title) {
                                    slide += '<div class="title">' + message.title + '</div>';
                                }
                                if (message.content) {
                                    slide += '<div class="content">' + message.content + '</div>';
                                }
                            }
                            slide += '</li>';
                            items.push(slide);
                        });
                        $(".message-slider .messageContent").html("<ul>" + items.join("") + "</ul>");
                        messageInfoSlider.run();
                    }
                },
                /**
                 * If error show offline existing message
                 */
                error: function () {
                    if ($(".message-slider li").length > 0) {
                        messageInfoSlider.run();
                    }
                },
            });
        }
    },
    moveRight: function () {
        var screenWidth = $(window).width();
        $('.message-slider ul').animate({
            left: -screenWidth
        }, 1000, function () {
            $('.message-slider li:first-child').appendTo('.message-slider ul');
            $('.message-slider ul').css('left', '');
        });
    },
    setFontSize: function () {
        $('.message-slider li').each(function (i, slide) {
            var $slide = $(slide);
            if ($slide.find("img").length > 0) {
                return true;
            }
            fixFontSize(slide, 20);
        });
    }
};

var flashMessage = {
    show: function () {
        if ($("footer .textSlide").length > 0) {
            $("footer .textSlide").removeClass("hidden");
            $("footer .info").addClass("hidden");
        }
    },
    hide: function () {
        $("footer .textSlide").addClass("hidden");
        $("footer .info").removeClass("hidden");
    },
}