<?php
class View
{
    function generate($content, $template, $data = null)
    {
        include 'application/views/' . $template;
    }
}