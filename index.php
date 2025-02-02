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
    'action' =>  (number_format($weight, 1) > number_format($goal_weight, 1)) ? 'light' : 'normal',
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
    if (!preg_match('/\.(gif|png|webp|jpeg|jpg)$/', $file)) {
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
  $todays_weight = floatval($todays_weight);

  //$start_timestamp = strtotime($settings->start_date . ' 12:00:00');
  //$end_timestamp = strtotime($settings->end_date . ' 12:00:00');
  //$num_days = ($end_timestamp - $start_timestamp) / SECONDS_PER_DAY;
  if ($todays_weight) {
    $measurements[$today] = $todays_weight;
  }
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

  if (!empty($measurements[$today]) && round($measurements[$today], 1) <= round($settings->end_weight, 1)) {
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
  $todays_weight = $measurements[$today];
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
  <link rel="stylesheet" media="all" href="./styles.css" />
</head>
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

<?php if ($goal_info) { ?>
<div class="todays-action todays-action--<?php print $goal_info['action'] ?>">
  <div class="todays-action-text">
  <?php print $goal_info['action_text'] ?>
  </div>
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

<form class="settings-form" method="post">
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
      <div class="label">To access your data on a different device, go to this URL on your other device:</div>
      <?php $import_url =  $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)) . '?import=' .  $id; ?>
      <div class="value"><a href="<?php print $import_url ?>"><?php print $import_url ?></a></div>
    </div>
  </details>
</form>

<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
const chartDataValues = <?php print json_encode(array_values($chart_data)); ?>;
</script>
<script type="text/javascript" src="./scripts.js"></script>
</body>
</html>
