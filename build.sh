#!/bin/bash

# Disable debug
sed -i.bak  "s@^const.DEBUG.*;@const DEBUG = false;@" qqmusic.php

sed -i.bak "s@^const.NEED_TRANSLATION.*;@const NEED_TRANSLATION = true;@" qqmusic.php
tar czf qqmusic.aum qqmusic.php INFO

# Clean up
rm qqmusic.php.bak
git checkout qqmusic.php
