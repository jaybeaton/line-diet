if (chartDataValues) {
  google.charts.load('current', {packages: ['corechart', 'line']});
  google.charts.setOnLoadCallback(drawAxisTickColors);
}

function drawAxisTickColors() {
  var data = new google.visualization.DataTable();
  data.addColumn('number', 'Day');
  data.addColumn('number', 'Goal');
  data.addColumn('number', 'Actual');
  data.addRows(chartDataValues);

  var options = {
    hAxis: {
      title: '',
      textStyle: {
        color: '#01579b',
      },
      titleTextStyle: {
        color: '#01579b',
      }
    },
    vAxis: {
      title: '',
      textStyle: {
        color: '#1a237e',
      },
      titleTextStyle: {
        color: '#1a237e',
      }
    },
    chartArea: {
      width: '85%',
      height: '60%'
    },
    legend: 'none',
    colors: ['#a52714', '#097138']
  };
  var chart = new google.visualization.LineChart(document.getElementById('chart'));
  chart.draw(data, options);
}
