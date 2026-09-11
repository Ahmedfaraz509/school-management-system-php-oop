<?php
// helpers.php
// Common helper functions used across all pages

/**
 * Get subject color class
 */
function getSubjectColor($subject_title)
{
  $colors = [
    'Mathematics' => 's-math',
    'Physics' => 's-phy',
    'Computer' => 's-cs',
    'Chemistry' => 's-chem',
    'English' => 's-eng',
    'Urdu' => 's-urdu',
    'Biology' => 's-bio',
    'Economics' => 's-econ',
    'Accounting' => 's-acc',
    'Business' => 's-bus'
  ];

  foreach ($colors as $key => $color) {
    if (stripos($subject_title, $key) !== false) {
      return $color;
    }
  }
  return 's-math';
}

/**
 * Get subject icon
 */
function getSubjectIcon($subject_title)
{
  $icons = [
    'Mathematics' => 'bi-calculator',
    'Physics' => 'bi-lightning-charge',
    'Computer' => 'bi-pc-display',
    'Chemistry' => 'bi-droplet-half',
    'English' => 'bi-book',
    'Urdu' => 'bi-pen',
    'Biology' => 'bi-heart-pulse',
    'Economics' => 'bi-graph-up',
    'Accounting' => 'bi-cash-stack',
    'Business' => 'bi-briefcase'
  ];

  foreach ($icons as $key => $icon) {
    if (stripos($subject_title, $key) !== false) {
      return $icon;
    }
  }
  return 'bi-book';
}

/**
 * Get event color based on name
 */
function getEventColor($event_name)
{
  $colors = [
    'Exhibition' => 'teal',
    'Exam' => 'info',
    'Sports' => 'amber',
    'Cultural' => 'violet',
    'Community' => 'ok',
    'Competition' => 'danger',
    'Seminar' => 'info',
    'Workshop' => 'teal',
    'Festival' => 'ok',
    'Meeting' => 'violet'
  ];

  foreach ($colors as $key => $color) {
    if (stripos($event_name, $key) !== false) {
      return $color;
    }
  }
  return 'info';
}

/**
 * Get event icon based on name
 */
function getEventIcon($event_name)
{
  $icons = [
    'Exhibition' => 'bi-lightbulb',
    'Exam' => 'bi-pencil-square',
    'Sports' => 'bi-trophy',
    'Cultural' => 'bi-mic',
    'Community' => 'bi-tree',
    'Competition' => 'bi-robot',
    'Seminar' => 'bi-people',
    'Workshop' => 'bi-tools',
    'Festival' => 'bi-gift',
    'Meeting' => 'bi-chat'
  ];

  foreach ($icons as $key => $icon) {
    if (stripos($event_name, $key) !== false) {
      return $icon;
    }
  }
  return 'bi-calendar2-heart';
}

/**
 * Get initials from name
 */
function getInitials($name)
{
  if (empty($name))
    return 'U';
  $words = explode(' ', $name);
  $initials = '';
  foreach ($words as $word) {
    if (!empty($word)) {
      $initials .= strtoupper(substr($word, 0, 1));
    }
  }
  return substr($initials, 0, 2);
}

/**
 * Get avatar color based on name
 */
function getAvatarColor($name)
{
  $colors = [
    'Sara' => 'info',
    'Ahmed' => 'ok',
    'Farah' => 'amber',
    'Ali' => 'violet',
    'Hina' => 'teal',
    'Bilal' => 'ok',
    'Tariq' => 'amber',
    'Admin' => 'violet',
    'Accounts' => 'danger',
    'Examination' => 'info',
    'Library' => 'ok',
    'Khan' => 'info',
    'Raza' => 'ok',
    'Hassan' => 'violet',
    'Malik' => 'teal'
  ];

  foreach ($colors as $key => $color) {
    if (stripos($name, $key) !== false) {
      return $color;
    }
  }
  return 'info';
}

/**
 * Format currency
 */
function formatCurrency($amount)
{
  return 'Rs. ' . number_format($amount, 0);
}

/**
 * Format percentage
 */
function formatPercentage($value)
{
  return number_format($value, 1) . '%';
}

/**
 * Get status color class
 */
function getStatusColor($status)
{
  $colors = [
    'Paid' => 'p-ok',
    'Partial' => 'p-warn',
    'Unpaid' => 'p-grey',
    'Overdue' => 'p-danger',
    'Pending' => 'p-warn',
    'Confirmed' => 'p-ok',
    'Planning' => 'p-warn',
    'Completed' => 'p-grey',
    'Cancelled' => 'p-danger',
    'Active' => 'p-ok',
    'Inactive' => 'p-grey',
    'Scheduled' => 'p-info',
    'Ongoing' => 'p-warn',
    'Pass' => 'p-ok',
    'Fail' => 'p-danger'
  ];
  return $colors[$status] ?? 'p-grey';
}

/**
 * Get category color for notices
 */
function getCategoryColor($category)
{
  $colors = [
    'Academic' => 'p-teal',
    'Administrative' => 'p-violet',
    'Sports' => 'p-ok',
    'Holiday' => 'p-warn',
    'Event' => 'p-info'
  ];
  return $colors[$category] ?? 'p-grey';
}

/**
 * Get category icon for notices
 */
function getCategoryIcon($category)
{
  $icons = [
    'Academic' => 'bi-mortarboard',
    'Administrative' => 'bi-gear',
    'Sports' => 'bi-trophy',
    'Holiday' => 'bi-calendar-check',
    'Event' => 'bi-calendar2-heart'
  ];
  return $icons[$category] ?? 'bi-info-circle';
}

/**
 * Get category label
 */
function getCategoryLabel($category)
{
  $labels = [
    'Academic' => 'Academic',
    'Administrative' => 'Admin',
    'Sports' => 'Sports',
    'Holiday' => 'Holiday',
    'Event' => 'Event'
  ];
  return $labels[$category] ?? $category;
}

/**
 * Check if notice is important
 */
function isImportant($title)
{
  $keywords = ['Important', 'Mandatory', 'Urgent', 'Required', 'Action', 'Alert', 'Notice'];
  foreach ($keywords as $keyword) {
    if (stripos($title, $keyword) !== false) {
      return true;
    }
  }
  return false;
}

/**
 * Get payment method color
 */
function getPaymentMethodColor($method)
{
  $colors = [
    'Bank Transfer' => 'p-info',
    'Credit Card' => 'p-violet',
    'Cash' => 'p-teal',
    'Cheque' => 'p-warn',
    'Pay Order' => 'p-ok'
  ];
  return $colors[$method] ?? 'p-grey';
}

/**
 * Format message preview
 */
function formatPreview($content, $length = 80)
{
  $clean = strip_tags($content ?? '');
  if (strlen($clean) > $length) {
    return substr($clean, 0, $length) . '…';
  }
  return $clean;
}

/**
 * Get sender type label
 */
function getSenderTypeLabel($type)
{
  $labels = [
    'Admin' => 'Admin Office',
    'Teacher' => 'Teacher',
    'Student' => 'Student',
    'Parent' => 'Parent'
  ];
  return $labels[$type] ?? $type;
}
?>