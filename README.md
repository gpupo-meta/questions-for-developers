# Questions for Developers

## Requirements

- PHP >= *7.3*
- [Composer Dependency Manager](http://getcomposer.org)

## Install

    composer install

### Add custom config (optional)

    cp parameters.dist.yaml parameters.yaml

and modify ``extra_file`` in ``.env`` file

## Run

    bin/console questions:start

## Todo

Add new questions at ``data/`` directory
