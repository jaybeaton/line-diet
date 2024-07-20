<?php
const SECONDS_PER_DAY = 60 * 60 * 24;
const DATA_PATH = './data/';
const IMAGE_PATH = './images/';
const DEFAULT_IMAGE = 'default.gif';
const COOKIE_NAME = 'line_diet_id';

function get_goal_info($settings, $date, $weight) {
  $date_timestamp = strtotime($date . ' 12:00:00');
  $start_timestamp = strtotime($settings->start_date . ' 12:00:00');
  $end_timestamp = strtotime($settings->end_date . ' 12:00:00');
  $num_days = ($end_timestamp - $start_timestamp) / SECONDS_PER_DAY;
  $goal_weight = $settings->start_weight - ( ($date_timestamp - $start_timestamp) / ($end_timestamp - $start_timestamp) * ($settings->start_weight - $settings->end_weight) );
  return [
    'weight' => $goal_weight,
    'action' =>  ($weight > $goal_weight) ? 'light' : 'normal',
  ];
}

function save_data($id, $settings, $measurements) {
  $data = [
    'settings' => $settings,
    'measurements' => $measurements,
  ];
  $data = json_encode($data, JSON_PRETTY_PRINT);
  $filename = DATA_PATH . $id . '.json';
  file_put_contents($filename, $data);
}

function get_data($id) {
  $filename = DATA_PATH . $id . '.json';
  if ($data = @file_get_contents($filename)) {
    $data = json_decode($data);
    if (empty($data->settings)) {
      $data = FALSE;
    }
  }
  return $data;
}

function get_image() {
  $images = [];
  $files = scandir(IMAGE_PATH);
  foreach ($files as $file) {
    if (!str_ends_with($file, '.gif')) {
      continue;
    }
    $images[$file] = TRUE;
  } // Loop thru files in images directory.
  if (!empty($images[DEFAULT_IMAGE]) && count($images) > 1) {
    // Remove the default image.
    unset($images[DEFAULT_IMAGE]);
  }
  $images = array_keys($images);
  $index = rand(0, count($images) - 1);
  return $images[$index];
}

$success = [];
$reload = FALSE;
$id = NULL;
if (!empty($_REQUEST['import'])) {
  $id = $_REQUEST['import'];
  $reload = TRUE;
}
else {
  $id = $_COOKIE[COOKIE_NAME] ?? '';
}
if (!$id = preg_replace('/[^a-f0-9]/', '', $id)) {
  $id = md5(uniqid(microtime(), TRUE));
}
// Refresh the cookie.
setcookie(COOKIE_NAME, $id, time() + (10 * 365 * SECONDS_PER_DAY));
$save = FALSE;

if ($data = get_data($id)) {
  $measurements = (array) $data->measurements ?? [];
  $settings = $data->settings;
//  print '<pre>' . print_r($data, 1) . '</pre>';
}
else {
  $save = TRUE;
  $measurements = [];
  $settings = new StdClass();
  $settings->start_weight = '';
  $settings->start_date = '';
  $settings->end_weight = '';
  $settings->end_date = '';
}

if (!empty($_REQUEST['save'])) {
  $save = TRUE;
  $reload = TRUE;
  $settings->start_weight = $_REQUEST['start_weight'] ?? '';
  $settings->start_date = $_REQUEST['start_date'] ?? '';
  $settings->end_weight = $_REQUEST['end_weight'] ?? '';
  $settings->end_date = $_REQUEST['end_date'] ?? '';
}

//$measurements = [
//  '2024-01-02' => 188.0,
//  '2024-01-03' => 186.6,
//  '2024-01-04' => 185.2,
//  '2024-01-05' => 184.6,
//  '2024-01-06' => 184.6,
//  '2024-01-07' => 186.4,
//  '2024-01-08' => 186.6,
//  '2024-01-09' => 184.6,
//  '2024-01-10' => 183.4,
//];

$today = date('Y-m-d');
$today_timestamp = strtotime($today . ' 12:00:00');
$tomorrow = date('Y-m-d', $today_timestamp + SECONDS_PER_DAY);

if (!empty($_REQUEST['weigh_in'])) {
  $save = TRUE;
  $reload = TRUE;
  $todays_weight = $_REQUEST['weight'] ?? '';

  //$start_timestamp = strtotime($settings->start_date . ' 12:00:00');
  //$end_timestamp = strtotime($settings->end_date . ' 12:00:00');
  //$num_days = ($end_timestamp - $start_timestamp) / SECONDS_PER_DAY;
  $measurements[$today] = $todays_weight;
}

$chart_data = [];
$table = [];

if ($measurements) {

  $start_timestamp = strtotime($settings->start_date . ' 12:00:00');
  $end_timestamp = strtotime($settings->end_date . ' 12:00:00');
  $num_days = ($end_timestamp - $start_timestamp) / SECONDS_PER_DAY;
  $timestamp = $start_timestamp;
  for ($i = 1; $i <= $num_days; $i++) {
    $date = date('Y-m-d', $timestamp);
    $info = get_goal_info($settings, $date, 0);
    $chart_data[$date] = [
      $i,
      doubleval(number_format($info['weight'], 1)),
      (!empty($measurements[$date])) ? doubleval($measurements[$date]) : NULL,
    ];
    $timestamp += SECONDS_PER_DAY;
  }

  if (round($measurements[$today], 1) <= round($settings->end_weight, 1)) {
    // They hit their goal!
    $message = "You've hit your goal ";
    $days_delta = round(($today_timestamp - $end_timestamp) / SECONDS_PER_DAY);
    $before_after = ($days_delta <= 0) ? 'before' : 'after';
    $days_delta = abs($days_delta);
    if ($days_delta > 0) {
      $message .= $days_delta . ' ';
      $message .= ($days_delta == 1) ? 'day' : 'days';
      $message .= ' ' . $before_after;
    }
    else {
      $message .= 'on';
    }
    $message .= ' your deadline!';
    $days =
    $success = [
      'text' => $message,
      'image' => get_image(),
    ];
  }

  $table['header'] = [
    'Date',
    'Day',
    'Goal Weight',
    'Actual Weight',
    'Action',
    'Delta',
  ];
  $table['rows'] = [];
  $day = 1;
  foreach ($measurements as $date => $weight) {
    $info = get_goal_info($settings, $date, $weight);
    $timestamp = strtotime($date . ' 12:00:00');
    $delta = $weight - $info['weight'];
    if ($delta > 0) {
      $delta_sign = '+';
    }
    elseif ($delta < 0) {
      $delta_sign = '-';
    }
    else {
      $delta_sign = '';
    }
    $delta = abs($delta);
    $row = [
      'date' => date('l,<\b\r>j M Y', $timestamp),
      'day' => $day,
      'goal_weight' => number_format($info['weight'], 1),
      'actual_weight' => number_format($weight, 1),
      'action' => $info['action'],
      'delta' => $delta_sign . number_format($delta, 1),
    ];
    $table['rows'][] = $row;
    $day++;
  }
}

//  print '<pre>' . print_r($chart_data, 1) . '</pre>';

if ($save) {
  save_data($id, $settings, $measurements);
}

$goal_info = [];
if (!empty($measurements[$today])) {
  $goal_info = get_goal_info($settings, $today, $measurements[$today]);
  $goal_info['action_text'] = ($goal_info['action'] == 'light') ? 'Eat light' : 'Eat normal';
}

/*
2024-01-02,188.0
2024-01-03,186.6
2024-01-04,185.2
2024-01-05,184.6
2024-01-06,184.6
2024-01-07,186.4
2024-01-08,186.6
2024-01-09,184.6
2024-01-10,183.4
*/

//print '<pre>' . print_r($_REQUEST, 1) . '</pre>';
//print '<pre>' . print_r($table, 1) . '</pre>';

if ($reload) {
  $url = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
  header('Location: ' . $url, TRUE, 302);
  exit();
}
?>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="MobileOptimized" content="width" />
  <meta name="HandheldFriendly" content="true" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Line Diet</title>
  <link rel="shortcut icon" type="image/png" href="favicon.ico"/>
</head>
<style>
  /*https://piccalil.li/blog/a-more-modern-css-reset/*/
  /* Box sizing rules */
  *,
  *::before,
  *::after {
    box-sizing: border-box;
  }

  /* Prevent font size inflation */
  html {
    -moz-text-size-adjust: none;
    -webkit-text-size-adjust: none;
    text-size-adjust: none;
  }

  /* Remove default margin in favour of better control in authored CSS */
  body, h1, h2, h3, h4, p,
  figure, blockquote, dl, dd {
    margin-block-end: 0;
  }

  /* Remove list styles on ul, ol elements with a list role, which suggests default styling will be removed */
  ul[role='list'],
  ol[role='list'] {
    list-style: none;
  }

  /* Set core body defaults */
  body {
    min-height: 100vh;
    line-height: 1.5;
  }

  /* Set shorter line heights on headings and interactive elements */
  h1, h2, h3, h4,
  button, input, label {
    line-height: 1.1;
  }

  /* Balance text wrapping on headings */
  h1, h2,
  h3, h4 {
    text-wrap: balance;
  }

  /* A elements that don't have a class get default styles */
  a:not([class]) {
    text-decoration-skip-ink: auto;
    color: currentColor;
  }

  /* Make images easier to work with */
  img,
  picture {
    max-width: 100%;
    display: block;
  }

  /* Inherit fonts for inputs and buttons */
  input, button,
  textarea, select {
    font: inherit;
  }

  /* Make sure textareas without a rows attribute are not tiny */
  textarea:not([rows]) {
    min-height: 10em;
  }

  /* Anything that has been anchored to should have extra scroll margin */
  :target {
    scroll-margin-block: 5ex;
  }


  html {
    --light-red: #f2dede;
    --dark-red: #a94442;
    --light-green: #dff0d8;
    --dark-green: #3c763d;
    --dark-gray: #333;
    --text-color: var(--dark-gray);
    --button-text-color: #fff;
    --button-color: #007bff;
  }
  body {
    color: var(--text-color);
    font-size: 22px;
    font-family: 'Open Sans', sans-serif
  }
  details {
    border: 1px solid var(--text-color);
    border-radius: 5px;
    padding: 10px;
    margin-bottom: 10px;
  }
  summary {
    font-weight: bold;
  }
  .field {
    padding: 5px 0;
  }
  .field--id {
    font-size: 16px;
  }
  .label, label {
    display: block;
    font-weight: bold;
  }
  input {
    border: 1px solid var(--text-color);
    border-radius: 5px;
    padding: 5px;
  }
  input[type="submit"] {
    color: var(--button-text-color);
    background-color: var(--button-color);
    border: 1px solid;
    border-radius: 16px;
    padding: 10px;
    font-weight: bold;
  }
  input[type="submit"]:hover {
    background-color: var(--button-text-color);
    color: var(--button-color);
    border-color: var(--button-color);
  }
  .actions {
    padding: 5px 0;
  }
  .todays-action {
    border: 1px solid var(--text-color);
    border-radius: 5px;
    padding: 10px;
    font-size: 24px;
    font-weight: bold;
  }
  .todays-action--normal {
    border: 1px solid var(--dark-green);
    color: var(--dark-green);
    background-color: var(--light-green);
  }
  .todays-action--light {
    border-color: var(--dark-red);
    color: var(--dark-red);
    background-color: var(--light-red);
  }
  .success {
    width: 100%;
    padding: 30px 0;
  }
  .success .success-text {
    font-weight: bold;
    font-size: 36px;
    padding-bottom: 10px;
  }
  table {
    width: 95%;
  }
  tr.normal {
    background-color: var(--light-green);
  }
  tr.light {
    background-color: var(--light-red);
  }
  th, td {
    text-align: right;
    font-size: 14px;
  }
  th.header--date, td.align-left {
    text-align: left;
  }
</style>
<body>

<form method="post">

  <div class="field">
    <div class="label">Today</div>
    <div class="value"><?php print date('l, j M Y', $today_timestamp); ?></div>
  </div>

  <?php if (!empty($chart_data[$today])) { ?>
  <div class="field">
    <div class="label">Goal Weight</div>
    <div class="value"><?php print number_format($chart_data[$today][1], 1) ?></div>
  </div>
  <?php } ?>

  <div class="field">
    <label for="weight">Today's weight</label>
    <input type="number" id="weight" name="weight" value="<?php print (!empty($todays_weight)) ? number_format($todays_weight, 1) : '' ?>" step=".1">
  </div>
  <div class="actions">
    <input type="submit" name="weigh_in" value="Save">
  </div>

</form>

<?php if ($goal_info) { ?>
<div class="todays-action todays-action--<?php print $goal_info['action'] ?>">
  <div class="todays-action-text">
  <?php print $goal_info['action_text'] ?>
  </div>
</div>
<?php } ?>

<?php if ($success) { ?>
    <div class="success">
        <div class="success-text">
          Congratulations!<br>
          <?php print $success['text'] ?>
        </div>
        <?php if (!empty($success['image'])) { ?>
        <div class="success-image">
          <img alt="Congratulations!" src="./images/<?php print $success['image'] ?>">
        </div>
        <?php } ?>
    </div>
<?php } ?>

<div class="chart-wrapper">
  <div id="chart"></div>
</div>

<?php
if ($table) {
  print "<table>\n<tr>\n";
  foreach ($table['header'] as $header) {
    $class = 'header--' . strtolower(str_replace(' ', '-', $header));
    print "<th class='{$class}'>" . htmlentities($header) . "</th>\n";
  }
  print "</tr>\n";
  foreach ($table['rows'] as $row) {
    print "<tr class='{$row['action']}'>\n";
    print '<td class="align-left">' . $row['date'] . "</td>\n";
    print '<td>' . $row['day'] . "</td>\n";
    print '<td>' . $row['goal_weight'] . "</td>\n";
    print '<td>' . $row['actual_weight'] . "</td>\n";
    print '<td>' . ucfirst($row['action']) . "</td>\n";
    print '<td>' . $row['delta'] . "</td>\n";
    print "</tr>\n";
  }
  print "</table>\n";
}
?>

<?php if (!empty($chart_data[$tomorrow])) { ?>
  <div class="field">
    <div class="label">Tomorrow's Goal Weight</div>
    <div class="value"><?php print number_format($chart_data[$tomorrow][1], 1) ?></div>
  </div>
<?php } ?>

<form method="post">
  <details>
    <summary>Settings</summary>
    <div class="field">
      <label for="start_weight">Start weight</label>
      <input type="number" id="start_weight" name="start_weight" value="<?php print $settings->start_weight ? number_format($settings->start_weight, 1) : '' ?>" step=".1">
    </div>
    <div class="field">
      <label for="start_date">Start date</label>
      <input type="date" id="start_date" name="start_date" value="<?php print htmlentities($settings->start_date) ?>">
    </div>
    <div class="field">
      <label for="end_weight">Goal weight</label>
      <input type="number" id="end_weight" name="end_weight" value="<?php print $settings->end_weight ? number_format($settings->end_weight, 1) : '' ?>" step=".1">
    </div>
    <div class="field">
      <label for="end_date">Ending date</label>
      <input type="date" id="end_date" name="end_date" value="<?php print htmlentities($settings->end_date) ?>">
    </div>
    <div class="actions">
      <input type="submit" name="save" value="Save settings">
    </div>
    <div class="field field--id">
      <div class="label">ID</div>
      <div class="value"><?php print $id ?></div>
    </div>
  </details>
</form>

<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
const chartDataValues = <?php print json_encode(array_values($chart_data)); ?>;

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
</script>
</body>
</html>
