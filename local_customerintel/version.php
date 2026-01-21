<?php
/**
 * Customer Intelligence Dashboard - Version file
 *
 * @package    local_customerintel
 * @copyright  2024 Fused / Rubi Platform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_customerintel';
$plugin->version   = 2025102216;  // YYYYMMDDXX format - v16_stable Citation Domain Normalization & Evidence Diversity
$plugin->requires  = 2022041900;  // Requires Moodle 4.0+
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = 'v16_stable Citation Domain Normalization & Evidence Diversity Context';

// Dependencies
$plugin->dependencies = array();

// CustomerIntel v1.0.0 - Production Release
// Changelog: See CHANGELOG.md for complete release notes