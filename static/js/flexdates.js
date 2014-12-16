$("[data-collapse-group='assignment-divs']").click(function(){
    var $this = $(this);
    $("[data-collapse-group='assignment-divs']:not([data-target='" + $this.data("target") + "'])").each(function(){
        $($(this).data('target')).removeClass('in').addClass('collapse');
    });
});

$('[data-toggle=tooltip]').tooltip();

$(document).ready(function () {
  $('[data-toggle="offcanvas"]').click(function(){
    $('.row-offcanvas').toggleClass('active')
  });
  
});

//$(function() {
//  $("#teacher-selecter").selectpicker();
//  $("#coach-selecter").selectpicker();
//  $("#site-selecter").selectpicker();
//  $("#course-selecter").selectpicker();
//});

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

$('.flexdates-assignments-range').on('click',function(e){
    var li = $(this);
    var range = li.attr('id');
    var dt = new Date(); 
    var end_date = {
        'flexdates-assignments-today':dt,
        'flexdates-assignments-next-2':dt.getTime() + 2*24*3600*1000,
        'flexdates-assignments-next-3':dt.getTime() + 3*24*3600*1000,
        'flexdates-assignments-next-7':dt.getTime() + 7*24*3600*1000,
        'flexdates-assignments-next-30':dt.getTime() + 30*24*3600*1000,
        'flexdates-assignments-all-time':dt.getTime() + 720*24*3600*1000
    }[range];
    console.log(li.text());
    $('.flexdates-upcoming-assignments-dropdown-text').text(li.text());
    $('.upcoming-assignments').each(function () {
        var tr = $(this);
        tr.show();
        var due_date = new Date(tr.find('.upcoming-date').text());
        //console.log([dt.getTime(),end_date,due_date.getTime()]);
        if (due_date.getTime() < dt.getTime() || due_date.getTime() > end_date){
            tr.hide();
        }
    });  
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

$("#students-search").keyup(function(){
    var data = {
        "value":$(this).val()
    }
    $.ajax({
        type:"POST",
        dataType:"html",
        url:'ajax/student_search.php',
        data:data,
        success: function(msg){
            $('#students-list').html(msg);
        }
    })
    .fail(function(j,t,e){
        console.log('Error::!! '+e);
    });
});



$("#flexdates-apply-filters").on('click',function(e){
    var teacher = $("[title=teachers]").val();
    var coach = $("[title=coach]").val();
    var site = $("[title=site]").val();
    var course = $("[title=course]").val();
    console.log([teacher,coach,site,course]);
    
});
