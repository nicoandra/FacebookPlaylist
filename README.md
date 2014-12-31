FacebookPlaylist
================
This script will read a Facebook feed (event feed, user feed, etc.) and tries to find YouTube links on the status updates. In case a status update has a YouTube URL, the script will attempt to download the file, extract the audio and queue the song in Clementine.


Why?
====
I was planning to make a party, I wanted to let people decide what to listen to and this seemed to be a good thing to do.


What's missing?
===============
Post a reply on the status saying "The server queued this song, thanks!"


Dependencies
============
Ideally you'll use Composer. Checkout this repo and run composer install.
Also install youtube-dl avconv php5-cli and may be Redis.


How to use
==========
Run the file readSongs.php every often. Depending on how often you run it, change the setting in config.php

