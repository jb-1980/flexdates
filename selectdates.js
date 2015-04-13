$('.upcoming-assignments').each(function () {
    var tr = $(this);
    var start_date = new Date('11-04-2014');
    var end_date = new Date('11-15-2014');
    var date_string = tr.find('.upcoming-date').text();
    var day = new Date(tr.find('.upcoming-date').text());
    console.log(date_string);
    console.log(day);
    if (day >= end_date || day <= start_date){
        console.log('in here');
        tr.hide();
    }
});
