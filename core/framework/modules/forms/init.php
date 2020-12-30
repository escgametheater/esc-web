<?php
/**
 * Forms Helper
 * The aim of the forms module is to generate
 * and validate forms
 * Users specify the fields that the form should contain.
 * The html generation and the data validation are then
 * handled by this component
 *
 */

// Core/Base Form Class
require "form.php";

// Base Form Fields Abstract
require "formfield.php";

// Shared Form Field Classes
require "fields/core.php";

// Post Form Class
require "forms/postform.php";
