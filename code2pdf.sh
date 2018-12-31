: '
  This file is part of code2pdf.

  code2pdf is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  code2pdf is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with code2pdf.  If not, see <http://www.gnu.org/licenses/>.

  Copyright 2018 Zachary Young
  '


#!/bin/bash

#
# Globals
#
q=( ${@} )   # queue for arguments
is_dry=false # dry run flag
print_options="syntax:n,left:5pc,right:5pc,header:0,number:n"

# 
# Flag functions
# 
function to-dir {
  dir=$1
  if [ ! -d "$1" ]; then
      echo "\"$dir\" is not a directory"
      return
  fi
  output_dir=${dir%/}
}

function print-help {
/usr/bin/cat << ENDOFMESSAGE

Usage: code2pdf [options] {file...}

Options:

  -d {dir},           Place resulting PDF(s) in dir.
  --to-dir {dir}      Default: $(pwd)

ENDOFMESSAGE
}

#
# Helper functions
#
function test_for_binaries {
  if ! command -v /usr/bin/vim > /dev/null 2>&1; then
    if ! command -v /usr/bin/nvim > /dev/null 2>&1; then
      echo "ERROR: vim or nvim must be installed"
      exit 1
    else
      vim_command=/usr/bin/nvim
    fi
  else
    vim_command=/usr/bin/vim
  fi

  if ! command -v /usr/bin/ps2pdf > /dev/null 2>&1; then
      echo "ERROR: ps2pdf must be installed"
      exit 1
  fi

  if ! command -v /usr/bin/rm > /dev/null 2>&1; then
      echo "ERROR: rm must be installed"
      exit 1
  fi
}

# Print help if no arguments present
if [[ ${#q[@]} == 0 ]]; then
  print-help
  exit 0
fi

#
# Consume every argument in q
#
while [[ ${#q[@]} -gt 0 ]]; do
  arg=${q[0]}

  # Only process flags
  # Allows us to append a list of files to process
  if [[ ${arg:0:1} != "-" ]]; then
    echo "Processing files: ${q[@]}"
    break;
  fi

  case $arg in
    -d | --to-dir)
      to-dir ${q[1]}
      q=(${q[@]:1}) # Remove the argument from queue
      ;;
    -h | --help)
      print-help
      exit 0
      ;;
    -r | --dry-run)
      is_dry=true
      ;;
    *)
      echo "ERROR: $arg is not a valid flag"
      exit 1
  esac

  # Remove the flag from q
  q=( ${q[@]:1} )
done

# Set default directory to PWD if none given
if [[ $output_dir == "" ]]; then
  output_dir="$(pwd)"
fi

# Make sure all binaries are installed
# Otherwise exit
test_for_binaries

# Do work on remaining arguments
for f in ${q[@]}; do
  if [ ! -f $f ]; then
    echo "ERROR: \"$f\" is not a file"
    continue
  fi

  fn=$(basename $f)

  echo "Creating $output_dir/$fn.pdf"
  
  # File -> PS
  $vim_command --headless -u NONE -i NONE -es -c "set printoptions=$print_options | hardcopy > $output_dir/tmpf.ps | q" "$f"

  # Fix title
  sed -i "1,/^%%EndComments/ s/^%%Title:.*/%%Title: $fn/" "$output_dir/tmpf.ps"

  # PS -> PDF
  /usr/bin/ps2pdf -q "$output_dir/tmpf.ps" "$output_dir"/"$fn"".pdf"

  # cleanup PS
  /usr/bin/rm -f "$output_dir/tmpf.ps"
done

