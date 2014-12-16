$(function() {
  $("#flexdates-datepicker-start").datepicker({
      todayHighlight:true,
      startDate:"Date()"
  });
  $("#flexdates-datepicker-end").datepicker({
      todayHighlight:true,
      startDate:"Date()"
  });
});

$("#flexdates-datepicker-start").on('changeDate', function(e){
      var d = e['format'](0,'M dd, yyyy');
      $("[name=datepicker_start]").val(d);
});

$("#flexdates-datepicker-end").on('changeDate', function(e){
      var d = e['format'](0,'M dd, yyyy');
      $("[name=datepicker_end]").val(d);
});

$('#flexdates-set-custom-range').on('click',function(e){
  var start_date = new Date($("[name='datepicker_start']").val());
  var end_date = new Date($("[name='datepicker_end']").val());
  var start_string = start_date.toDateString().split(' ');
  var end_string = end_date.toDateString().split(' ');
  var out_text = start_string[1]+' '+start_string[2]+' '+start_string[3]+' to '+end_string[1]+' '+end_string[2]+' '+end_string[3];
  $('.flexdates-upcoming-assignments-dropdown-text').text(out_text);
  $('.upcoming-assignments').each(function () {
        var tr = $(this);
        tr.show();
        var due_date = new Date(tr.find('.upcoming-date').text());
        console.log(start_date,end_date,due_date);
        if (due_date.getTime() < start_date.getTime() || due_date.getTime() > end_date.getTime()){
            tr.hide();
        }
  });
  $('#customdates-modal').modal('hide')
});

