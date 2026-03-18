<?php

return [
    'title' => 'Game Platform List ',
    'fields' => [
        'id' => 'ID',
        'code' => 'Game platform code',
        'name' => 'Game platform name',
        'cate_id' => 'Game type',
        'display_mode' => 'Game Display Mode',
        'logo' => 'Logo',
        'status' => 'State',
        'ratio' => 'Electronic game settlement ratio',
        'has_lobby' => 'Do you want to enter the lobby',
        'picture' => 'Client side image',
    ],
    'display_mode' => [
        1 => 'Landscape',
        2 => 'Portrait',
        3 => 'All',
    ],
    'display_mode_help' => 'Select game display orientation (Landscape/Portrait/All)',
    'game_platform' => 'Game Supplier Information',
    'action_error' => 'Operation failed',
    'action_success' => 'operation successful',
    'enter_game' => 'Enter the game hall',
    'enter_game_confirm' => 'Enter the game hall,Are you sure you want to enter the lobby of this game manufacturer?',
    'ratio_help' => 'The settlement ratio of electronic game platforms, with the remaining amount used as the profit base for promoters',
    'ratio_placeholder' => 'Please fill in the settlement ratio of the electronic game platform',
    'view_game' => 'View game',
    'player_not_fount' => 'No post management player account has been set up',
    'disable' => 'The gaming platform has been disabled',
];
