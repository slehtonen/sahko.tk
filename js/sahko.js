var avg;

function prices_today() {
    $.post( "api.php", { mode: "get_prices"}).done(function( data ) {

        var decodedData = jQuery.parseJSON(data);

        $("#min_price").html(decodedData.min + ' snt/kWh');
        $("#max_price").html(decodedData.max + ' snt/kWh');
        $("#price_now").html(decodedData.now + ' snt/kWh');
        $("#avg").html(decodedData.avg + ' snt/kWh');
    });
}
var now = new Date();
var nowHour = new Date(now.getFullYear(), now.getMonth(), now.getDate(), now.getHours());

var today = new Date();

Date.prototype.stdTimezoneOffset = function() {
    var jan = new Date(this.getFullYear(), 0, 1);
    var jul = new Date(this.getFullYear(), 6, 1);
    return Math.max(jan.getTimezoneOffset(), jul.getTimezoneOffset());
}

Date.prototype.dst = function() {
    return this.getTimezoneOffset() < this.stdTimezoneOffset();
}
if (today.dst()) {
    nowHour = nowHour / 1000 * 1000 + 10800000;
} else {
    nowHour = nowHour / 1000 * 1000 + 7200000;
}

var chart;

function function1(callback) {
    var initialZoom = 2;
    var isMobile = window.matchMedia("only screen and (max-width: 800px)");

    if (isMobile.matches) {
        initialZoom = 1;
    }
    $.getJSON('api.php?callback=?', function (data) {
        Highcharts.setOptions({
            lang: {
                months: ['Tammikuu', 'Helmikuu', 'Maaliskuu', 'Huhtikuu', 'Toukokuu', 'Kesäkuu',  'Heinäkuu', 'Elokuu', 'Syyskuu', 'Lokakuu', 'Marraskuu', 'Joulukuu'],
                weekdays: ['Sunnuntai', 'Maanantai', 'Tiistai', 'Keskiviikko', 'Torstai', 'Perjantai', 'Lauantai'],
                shortMonths:['Tammi', 'Helmi', 'Maalis', 'Huhti','Touko', 'Kesä', 'Heinä', 'Elo', 'Syys', 'Loka', 'Marras', 'Joulu']
            }
        });

        var dataObject = {
            colors: ['#252839'], /* line color */
            navigator: {
                maskFill: 'rgba(37, 40, 57, 0.55)', /* navigator color */
                series: {
                   	type: 'areaspline',
                   	color: '#252839',
                   	fillOpacity: 0.05,
                   	dataGrouping: {
                   		smoothed: true
                   	},
                   	lineWidth: 1,
                   	marker: {
                   		enabled: false
                   	}
                }
            },
            chart: {
                renderTo: 'container',
                pinchType: 'none'
            },
            legend: {
                enabled:false
            },
            rangeSelector : {
                selected : initialZoom,
                buttons: [{
                    type: 'day',
                    count: 1,
                    text: '1 pv'
                }, {
                    type: 'day',
                    count: 2,
                    text: '2 pv'
                }, {
                    type: 'day',
                    count: 3,
                    text: '3 pv'
                }, {
                    type: 'day',
                    count: 7,
                    text: '7 pv'
                }, {
                    type: 'day',
                    count: 14,
                    text: '14 pv'
                }]
            },
            tooltip: {
                xDateFormat: '%A<br />%H:%M %e.%m.%Y',
                pointFormat: '{series.name}: {point.y} snt/kWh<br/>',
                valueDecimals: 2,
                zIndex: 9998,
                useHTML: true
            },
            yAxis: [{
                title: {
                    text: 'hinta c/kWh'
                },
                minRange: 8,
                min: 0,
                tickInterval:1,
                opposite:false,
                plotLines: [{
                    color: 'red',
                    label: {
                       // text: '7 vrk keskihinta ' + avg + "snt/kWh",
                    },
                    value: avg,
                    width: '1',
                    useHTML: true

                }]
            },
            {
                title: {
                    text: 'hinta c/kWh'
                },
                linkedTo:0,
                opposite:true
            }],
            xAxis: {
                type: 'datetime',
                plotLines: [{
                    color: '#f2b632',
                    value: nowHour,
                    width: '4',
                    useHTML: true

                }]
            },
            series: [{
                name: 'Hinta',
                zIndex: 200,
                // color: '#000000',
                useHTML: true,
             //   type:'column',
                data:data
            }]
        };

        chart = new Highcharts.StockChart(dataObject);
        callback();
    });
}

function update() {
    for (var i = 0; i < chart.series[0].points.length; i++) {

        if (chart.series[0].points[i].x == nowHour) {
            chart.series[0].points[i].update({
                color: '#00FFFF'
            });
            chart.series[0].points[i].select();
            chart.series[0].points[i].select();
        }
    }
}

prices_today();
function1(function() {
    // update();
});
