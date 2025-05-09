# DB Class Usage Guide

This document provides a basic guide on how to use the `DB` class for interacting with your database.

## Basic Usage

```php
// GET all
$stations = DB::table('stations')->select('name,type')->get();

// first or null
$user = DB::table('users')->where(['phone' => '911'])->select('*')->first();

// Insert
DB::table('users')->insert(['name' => 'MHY', 'phone' => '911']);

// Update
DB::table('users')->where(['phone' => 911])->update(['name' => 'Jani']);


// Delete
DB::table('users')->where(['phone' => 911])->delete();


//////// RAW
$stations = DB::rawQuery('SELECT name, type FROM stations');

// INSERT query example
DB::rawQuery('INSERT INTO stations (name, type) VALUES (?, ?)', ['Station 1', 'Type A']);

// UPDATE query example
DB::rawQuery('UPDATE stations SET name = ? WHERE id = ?', ['New Station Name', 1]);

// DELETE query example
DB::rawQuery('DELETE FROM stations WHERE id = ?', [1]);