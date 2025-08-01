<?php

namespace Drupal\Core\Datetime\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Variable;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Datetime\Entity\DateFormat;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Security\DoTrustedCallbackTrait;
use Drupal\Core\Security\StaticTrustedCallbackHelper;

/**
 * Provides a datetime element.
 */
#[FormElement('datetime')]
class Datetime extends DateElementBase {

  use DoTrustedCallbackTrait;

  /**
   * @var \DateTimeInterface
   */
  protected static $dateExample;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $date_format = '';
    $time_format = '';
    // Date formats cannot be loaded during install or update.
    if (!defined('MAINTENANCE_MODE')) {
      if ($date_format_entity = DateFormat::load('html_date')) {
        /** @var \Drupal\Core\Datetime\DateFormatInterface $date_format_entity */
        $date_format = $date_format_entity->getPattern();
      }
      if ($time_format_entity = DateFormat::load('html_time')) {
        /** @var \Drupal\Core\Datetime\DateFormatInterface $time_format_entity */
        $time_format = $time_format_entity->getPattern();
      }
    }

    // Note that since this information is cached, the #date_timezone property
    // is not set here, as this needs to vary potentially by-user.
    return [
      '#input' => TRUE,
      '#element_validate' => [
        [static::class, 'validateDatetime'],
      ],
      '#process' => [
        [static::class, 'processDatetime'],
        [static::class, 'processAjaxForm'],
        [static::class, 'processGroup'],
      ],
      '#pre_render' => [
        [static::class, 'preRenderGroup'],
      ],
      '#theme' => 'datetime_form',
      '#theme_wrappers' => ['datetime_wrapper'],
      '#date_date_format' => $date_format,
      '#date_date_element' => 'date',
      '#date_date_callbacks' => [],
      '#date_time_format' => $time_format,
      '#date_time_element' => 'time',
      '#date_time_callbacks' => [],
      '#date_year_range' => '1900:2050',
      '#date_increment' => 1,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    $element += ['#date_timezone' => date_default_timezone_get()];

    if ($input !== FALSE) {
      if ($element['#date_date_element'] === 'datetime-local' && !empty($input['date'])) {
        // With a datetime-local input, the date value is always normalized to
        // the format Y-m-d\TH:i
        // @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/datetime-local
        // 'html_datetime' is not a valid format to pass to
        // DrupalDateTime::createFromFormat()
        [$date_input, $time_input] = explode('T', $input['date']);
        $date_format = DateFormat::load('html_date')->getPattern();
        $time_format = DateFormat::load('html_time')->getPattern();
      }
      else {
        $date_input = $element['#date_date_element'] != 'none' && !empty($input['date']) ? $input['date'] : '';
        $time_input = $element['#date_time_element'] != 'none' && !empty($input['time']) ? $input['time'] : '';
        $date_format = $element['#date_date_format'] != 'none' ? static::getHtml5DateFormat($element) : '';
        $time_format = $element['#date_time_element'] != 'none' ? static::getHtml5TimeFormat($element) : '';
      }

      // Seconds will be omitted in a post in case there's no entry.
      if (!empty($time_input) && strlen($time_input) == 5) {
        $time_input .= ':00';
      }

      try {
        $date_time_format = trim($date_format . ' ' . $time_format);
        $date_time_input = trim($date_input . ' ' . $time_input);
        $date = DrupalDateTime::createFromFormat($date_time_format, $date_time_input, $element['#date_timezone']);
      }
      catch (\Exception) {
        $date = NULL;
      }
      $input = [
        'date'   => $date_input,
        'time'   => $time_input,
        'object' => $date,
      ];
    }
    else {
      $date = $element['#default_value'] ?? NULL;
      if ($date instanceof DrupalDateTime && !$date->hasErrors()) {
        $date->setTimezone(new \DateTimeZone($element['#date_timezone']));
        $input = [
          'date'   => $date->format($element['#date_date_format']),
          'time'   => $date->format($element['#date_time_format']),
          'object' => $date,
        ];
      }
      else {
        $input = [
          'date'   => '',
          'time'   => '',
          'object' => NULL,
        ];
      }
    }
    return $input;
  }

  /**
   * Expands a datetime element type into date and/or time elements.
   *
   * All form elements are designed to have sane defaults so any or all can be
   * omitted. Both the date and time components are configurable so they can be
   * output as HTML5 datetime elements or not, as desired.
   *
   * Examples of possible configurations include:
   *   HTML5 date and time:
   *     #date_date_element = 'date';
   *     #date_time_element = 'time';
   *   HTML5 datetime:
   *     #date_date_element = 'datetime';
   *     #date_time_element = 'none';
   *   HTML5 time only:
   *     #date_date_element = 'none';
   *     #date_time_element = 'time'
   *   Non-HTML5:
   *     #date_date_element = 'text';
   *     #date_time_element = 'text';
   *
   * Required settings:
   *   - #default_value: A DrupalDateTime object, adjusted to the proper local
   *     timezone. Converting a date stored in the database from UTC to the
   *     local zone and converting it back to UTC before storing it is not
   *     handled here. This element accepts a date as the default value, and
   *     then converts the user input strings back into a new date object on
   *     submission. No timezone adjustment is performed.
   * Optional properties include:
   *   - #date_date_format: A date format string that describes the format that
   *     should be displayed to the end user for the date. When using HTML5
   *     elements the format MUST use the appropriate HTML5 format for that
   *     element, no other format will work. See the
   *     DateFormatterInterface::format() function for a list of the possible
   *     formats and HTML5 standards for the HTML5 requirements. Defaults to the
   *     right HTML5 format for the chosen element if an HTML5 element is used,
   *     otherwise defaults to DateFormat::load('html_date')->getPattern().
   *   - #date_date_element: The date element. Options are:
   *     - datetime: Use the HTML5 datetime element type.
   *     - datetime-local: Use the HTML5 datetime-local element type.
   *     - date: Use the HTML5 date element type.
   *     - text: No HTML5 element, use a normal text field.
   *     - none: Do not display a date element.
   *   - #date_date_callbacks: Array of optional callbacks for the date element.
   *   - #date_time_element: The time element. Options are:
   *     - time: Use an HTML5 time element type.
   *     - text: No HTML5 element, use a normal text field.
   *     - none: Do not display a time element.
   *   - #date_time_format: A date format string that describes the format that
   *     should be displayed to the end user for the time. When using HTML5
   *     elements the format MUST use the appropriate HTML5 format for that
   *     element, no other format will work. See the
   *     DateFormatterInterface::format() function for a list of the possible
   *     formats and HTML5 standards for the HTML5 requirements. Defaults to the
   *     right HTML5 format for the chosen element if an HTML5 element is used,
   *     otherwise defaults to DateFormat::load('html_time')->getPattern().
   *   - #date_time_callbacks: An array of optional callbacks for the time
   *     element. Can be used to add a jQuery timepicker or an 'All day'
   *     checkbox.
   *   - #date_year_range: A description of the range of years to allow, like
   *     '1900:2050', '-3:+3' or '2000:+3', where the first value describes the
   *     earliest year and the second the latest year in the range. A year
   *     in either position means that specific year. A +/- value describes a
   *     dynamic value that is that many years earlier or later than the current
   *     year at the time the form is displayed. Used in HTML5 min/max date
   *     settings. Defaults to '1900:2050'.
   *   - #date_increment: The interval (step) to use when incrementing or
   *     decrementing time, in seconds. For example, if this value is set to 30,
   *     time increases (or decreases) in steps of 30 seconds (00:00:00,
   *     00:00:30, 00:01:00, and so on.) If this value is a multiple of 60, the
   *     "seconds"-component will not be shown in the input. Used for HTML5 step
   *     values. Defaults to 1 to show every second.
   *   - #date_timezone: The Time Zone Identifier (TZID) to use when displaying
   *     or interpreting dates, i.e: 'Asia/Kolkata'. Defaults to the value
   *     returned by date_default_timezone_get().
   *
   * Example usage:
   * @code
   *   $form = [
   *     '#type' => 'datetime',
   *     '#default_value' => new DrupalDateTime('2000-01-01 00:00:00'),
   *     '#date_date_element' => 'date',
   *     '#date_time_element' => 'none',
   *     '#date_year_range' => '2010:+3',
   *     '#date_timezone' => 'Asia/Kolkata',
   *   ];
   * @endcode
   *
   * @param array $element
   *   The form element whose value is being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The form element whose value has been processed.
   *
   * @see \Drupal\Core\Datetime\DateFormatterInterface::format()
   */
  public static function processDatetime(&$element, FormStateInterface $form_state, &$complete_form) {
    $format_settings = [];
    // The value callback has populated the #value array.
    $date = !empty($element['#value']['object']) ? $element['#value']['object'] : NULL;

    $element['#tree'] = TRUE;

    if ($element['#date_date_element'] != 'none') {

      $date_format = $element['#date_date_element'] != 'none' ? static::getHtml5DateFormat($element) : '';
      // With a datetime-local input, the date value is always normalized to
      // the format Y-m-d\TH:i
      // @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/datetime-local
      // 'html_datetime' returned by static::getHtml5DateFormat($element) is not
      // a valid format.
      // @see https://www.drupal.org/project/drupal/issues/3505318
      if ($element['#date_date_element'] === 'datetime-local') {
        $date_format = DateFormat::load('html_date')->getPattern() . '\T' . DateFormat::load('html_time')->getPattern();
      }
      $date_value = !empty($date) ? $date->format($date_format, $format_settings) : $element['#value']['date'];

      // Creating format examples on every individual date item is messy, and
      // placeholders are invalid for HTML5 date and datetime, so an example
      // format is appended to the title to appear in tooltips.
      $extra_attributes = [
        'type' => $element['#date_date_element'],
      ];

      // Adds the HTML5 date attributes.
      if ($date instanceof DrupalDateTime && !$date->hasErrors()) {
        $html5_min = clone($date);
        $range = static::datetimeRangeYears($element['#date_year_range'], $date);
        $html5_min->setDate($range[0], 1, 1)->setTime(0, 0, 0);
        $html5_max = clone($date);
        $html5_max->setDate($range[1], 12, 31)->setTime(23, 59, 59);

        $extra_attributes += [
          'min' => $html5_min->format($date_format, $format_settings),
          'max' => $html5_max->format($date_format, $format_settings),
        ];
      }

      $element['date'] = [
        '#type' => 'date',
        '#title' => t('Date'),
        '#title_display' => 'invisible',
        '#value' => $date_value,
        '#attributes' => $element['#attributes'] + $extra_attributes,
        '#required' => $element['#required'],
        '#size' => max(12, strlen($element['#value']['date'])),
        '#error_no_message' => TRUE,
        '#date_date_format' => $element['#date_date_format'],
      ];

      // Allows custom callbacks to alter the element.
      if (!empty($element['#date_date_callbacks'])) {
        foreach ($element['#date_date_callbacks'] as $callback) {
          $message = sprintf('DateTime element #date_date_callbacks callbacks must be methods of a class that implements \Drupal\Core\Security\TrustedCallbackInterface or be an anonymous function. The callback was %s. See https://www.drupal.org/node/3217966', Variable::callableToString($callback));
          StaticTrustedCallbackHelper::callback($callback, [&$element, $form_state, $date], $message);
        }
      }
    }

    if ($element['#date_time_element'] != 'none') {

      $time_format = $element['#date_time_element'] != 'none' ? static::getHtml5TimeFormat($element) : '';
      $time_value = !empty($date) ? $date->format($time_format, $format_settings) : $element['#value']['time'];

      // Adds the HTML5 attributes.
      $extra_attributes = [
        'type' => $element['#date_time_element'],
        'step' => $element['#date_increment'],
      ];
      $element['time'] = [
        '#type' => 'date',
        '#title' => t('Time'),
        '#title_display' => 'invisible',
        '#value' => $time_value,
        '#attributes' => $element['#attributes'] + $extra_attributes,
        '#required' => $element['#required'],
        '#size' => 12,
        '#error_no_message' => TRUE,
      ];

      // Allows custom callbacks to alter the element.
      if (!empty($element['#date_time_callbacks'])) {
        foreach ($element['#date_time_callbacks'] as $callback) {
          $message = sprintf('DateTime element #date_time_callbacks callbacks must be methods of a class that implements \Drupal\Core\Security\TrustedCallbackInterface or be an anonymous function. The callback was %s. See https://www.drupal.org/node/3217966', Variable::callableToString($callback));
          StaticTrustedCallbackHelper::callback($callback, [&$element, $form_state, $date], $message);
        }
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function processAjaxForm(&$element, FormStateInterface $form_state, &$complete_form) {
    $element = parent::processAjaxForm($element, $form_state, $complete_form);

    // Copy the #ajax settings to the child elements.
    if (isset($element['#ajax'])) {
      if (isset($element['date'])) {
        $element['date']['#ajax'] = $element['#ajax'];
      }
      if (isset($element['time'])) {
        $element['time']['#ajax'] = $element['#ajax'];
      }
    }

    return $element;
  }

  /**
   * Validation callback for a datetime element.
   *
   * If the date is valid, the date object created from the user input is set in
   * the form for use by the caller. The work of compiling the user input back
   * into a date object is handled by the value callback, so we can use it here.
   * We also have the raw input available for validation testing.
   *
   * @param array $element
   *   The form element whose value is being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateDatetime(&$element, FormStateInterface $form_state, &$complete_form) {
    $input_exists = FALSE;
    $input = NestedArray::getValue($form_state->getValues(), $element['#parents'], $input_exists);
    if ($input_exists) {

      $title = static::getElementTitle($element, $complete_form);

      // If there's empty input and the field is not required, set it to empty.
      if (empty($input['date']) && empty($input['time']) && !$element['#required']) {
        $form_state->setValueForElement($element, NULL);
      }
      // If there's empty input and the field is required, set an error. A
      // reminder of the required format in the message provides a good UX.
      elseif (empty($input['date']) && empty($input['time']) && $element['#required']) {
        if (isset($element['#required_error'])) {
          $form_state->setError($element, $element['#required_error']);
        }
        else {
          $form_state->setError($element, t('The %field date is required.', ['%field' => $title]));
        }
      }
      else {
        // If the date is valid, set it.
        $date = $input['object'];
        if ($date instanceof DrupalDateTime && !$date->hasErrors()) {
          $form_state->setValueForElement($element, $date);
        }
        // If the date is invalid, set an error. A reminder of the required
        // format in the message provides a good UX.
        else {
          $form_state->setError($element, t('The %field date is invalid. Enter a date in the correct format.', ['%field' => $title]));
        }
      }
    }
  }

  /**
   * Retrieves the right format for an HTML5 date element.
   *
   * The format is important because these elements will not work with any other
   * format.
   *
   * @param array $element
   *   The $element to assess.
   *
   * @return string
   *   Returns the right format for the date element, or the original format
   *   if this is not an HTML5 element.
   */
  protected static function getHtml5DateFormat($element) {
    switch ($element['#date_date_element']) {
      case 'date':
        return DateFormat::load('html_date')->getPattern();

      case 'datetime':
      case 'datetime-local':
        return DateFormat::load('html_datetime')->getPattern();

      default:
        return $element['#date_date_format'];
    }
  }

  /**
   * Retrieves the right format for an HTML5 time element.
   *
   * The format is important because these elements will not work with any other
   * format.
   *
   * @param array $element
   *   The $element to assess.
   *
   * @return string
   *   Returns the right format for the time element, or the original format
   *   if this is not an HTML5 element.
   */
  protected static function getHtml5TimeFormat($element) {
    switch ($element['#date_time_element']) {
      case 'time':
        return DateFormat::load('html_time')->getPattern();

      default:
        return $element['#date_time_format'];
    }
  }

}
