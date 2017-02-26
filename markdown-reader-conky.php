<?php
/**
 * @file
 * In conky we only want to display todos that are one week into the future
 * and not done, plus any previous todos that are not done.
 */

/**
 * Parse the markdown file for a given course.
 *
 * @param string $course
 *   The name and folder name of a course.
 * @param bool $include_done
 *   Whether items that are done should be included.
 *
 * @return array
 *   List of items for the given course.
 */
function parse_markdown($course, $include_done = TRUE) {

  $file_path = __DIR__ . "/$course/0-deadlines.md";

  if (!is_file($file_path)) {
    print "'$file_path' is not a file.\n";
    exit(2);
  }

  $handle = fopen($file_path, "r");

  if (!$handle) {
    print "Could not read file '$file_path'";
    exit(3);
  }

  $deadlines = array();
  $date_str = NULL;
  while (($line = fgets($handle)) !== FALSE) {
    $matches = array();
    preg_match('/## (?<year>\d{4})-(?<month>\d{2})-(?<date>\d{2}) (?<headline>.*)/', $line, $matches);
    if (!empty($matches)) {
      // Remove all numeric keys as they are duplicates.
      $deadline = array_filter($matches, function ($k) { return !is_numeric($k); }, ARRAY_FILTER_USE_KEY);
      // If there is a $current_headline, then it is now finished
      // processing, so we add it to $deadlines and override it.
      $date_str = format_date_from_deadline($deadline);
    }
    else {
      // This is not a headline so it must be items. We add the items to
      // the $deadlines array indexed by the current $date_str.
      $line = trim($line);
      if (empty($line)) {
        continue;
      }
      $item_matches = array();
      if (preg_match('/\* (?<text>.*)/', $line, $item_matches)) {
        $done = substr($item_matches['text'], -4) == 'DONE';
        if (!$done || $include_done) {
          if (!isset($deadlines[$date_str][$course])) {
            $deadlines[$date_str][$course] = array();
          }
          $deadlines[$date_str][$course][] = array(
            'text' => trim($done ? substr($item_matches['text'], 0, -4) : $item_matches['text']),
            'done' => $done,
          );
        }
      }
    }
  }

  fclose($handle);

  return $deadlines;
}

/**
 * Format dates.
 *
 * @param array $deadline
 *   A deadline item as a keyed array.
 * @param string $format
 *   The format to render the date into.

 * @return string
 *   The rendered date.
 */
function format_date_from_deadline($deadline, $format = 'Y-m-d') {
  $date = new DateTime();
  $date->setDate($deadline['year'], $deadline['month'], $deadline['date']);
  return $date->format($format);
}

/**
 * Truncate text given a max length.
 *
 * @param string $text
 *   Text to be truncated.
 * @param int $max_len
 *   Max length of text.

 * @return string
 *   The (possibly) truncated text.
 */
function truncate($text, $max_len) {
    return substr_replace($text, '...', $max_len);
}

/* End of utility functions. */

// Allow max length of the items to be configurable.
$max_text_len = 30;
if (count($argv) > 1 && is_int($argv[1])) {
  $max_text_len = $argv[1];
}

$deadlines = array();
// List of courses where the key is the folder and the value is the abbreviation used when printing each item.
$courses = array(
  '2-advanced-programming' => 'AP',
  '2-mobile-app-development' => 'MAD',
  '2-pervasive-computing' => 'PC',
  '2-security' => 'SEC',
);
foreach (array_keys($courses) as $course) {
  $new_deadlines = parse_markdown($course, FALSE);
  $deadlines = array_merge_recursive($deadlines, $new_deadlines);
}

// Sort by array keys, i.e. date.
ksort($deadlines);

// Remove entries more than 7 days into the future.
$next_week = new DateTime();
$next_week = $next_week->modify('+7 days');
$deadlines = array_filter($deadlines, function($key) use ($next_week) {
  $date = DateTime::createFromFormat('Y-m-d', $key);
  return $date < $next_week;
}, ARRAY_FILTER_USE_KEY);

// Output.
$today = new DateTIme();
foreach ($deadlines as $date_str => $course_data) {
  $date = DateTime::createFromFormat('Y-m-d', $date_str);
  // Deadlines that have passed will have the date header printed in red in conky.
  $text_format = ($date < $today) ? "\${color red}%s\${color}\n" : "%s\n";
  print sprintf($text_format, $date->format('D, M jS'));

  foreach ($course_data as $course => $items) {
    foreach ($items as $item) {
      $text = truncate($item['text'], $max_text_len);
      print sprintf("\${color lightgrey}[%s] %s\${color}\n", $courses[$course], $text);
    }
  }
  print "\n";
}
