$(".date").text(dateTime.getCurrentDate(lang));
$('.current-time').text(dateTime.getCurrentTime(true));
setInterval(function () {
    $('.current-time').text(dateTime.getCurrentTime(true));
}, 1000);

const widget = $('.widget');
$('iframe').resizable();
$.ajax({
    url: widget.data("remote"),
    headers: {'Api-Access-Token': widget.data("apiAccessToken")},
    success: function (mosque) {
        // hijri date
        $(".hijriDate").text(writeIslamicDate(mosque.hijriAdjustment, lang));

        // shuruq
        $('.shuruq .time').text(mosque.shuruq);

        // jumua
        $('.jumua .time').text(mosque.jumua);

        // times
        $.each(mosque.times, function (i, time) {
            $('.prayers .time').eq(i).text(time);
        });

        //iqama
        $.each(mosque.iqama, function (i, time) {
            var iqama = time + "'";
            if (mosque.fixedIqama[i] !== "") {
                iqama = mosque.fixedIqama[i];
            }

            $('.prayers .iqama').eq(i).text(iqama);
        });
    }
});
