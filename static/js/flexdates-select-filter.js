$(function() {
  $("#teacher-selecter").selectpicker();
  $("#coach-selecter").selectpicker();
  $("#site-selecter").selectpicker();
  $("#course-selecter").selectpicker();
});

$("#flexdates-apply-filters").on('click',function(e){
    var teacher = $("#teacher-selecter").val();
    var coach = $("#coach-selecter").val();
    var site = $("#site-selecter").val();
    var course = $("#course-selecter").val();
    console.log([teacher,coach,site,course]);
    
});
