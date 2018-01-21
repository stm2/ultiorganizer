# Database Schema

## uo_accreditationlog

 * foreign keys: 
    - player (uo_player, n?:1)
    - team (uo_team, n?:1)
    - userid (uo_user, n?:1)
    - source (???)
    - game (uo_game, n?:1)
 
## uo_club

 * foreign keys
    - country (uo_country, n?:1)
    - image (?)
    - profile_image (?)

## uo_comment

 * foreign keys
    - ???

## uo_country

## uo_database

## uo_dbtranslations

 * dropped
 

## uo_defense

 * foreign keys:
    - game (n!:1)
    

## uo_enrolledteam

 * foreign keys:
    - series (uo_series, n!:1)
    - userid (n!:1)

## uo_event_log

 * foreign keys:
    - user_id (n!:1)??
    - category (---, n!:1)
    - type (---, n!:1)
    - id1, id2 (n?:1)
    - source (???)
 
## uo_game

 * foreign keys
    - hometeam (n?:1)?
    - visitorteam (n?:1)
    - reservation (n?:1)
    - pool (n?:1)??
    - respteam (n?:1)
    - resppers (n?:1)
    - homesotg (???)
    - scheduling_name_home (n?:1)?
    - scheduling_name_visitor (n?:1)?
    - name (uo_scheduling_name, 1:1!
 
## uo_game_pool

 * foreign keys
    - game (n!:1)
    - pool (n!:1)
 
## uo_gamevent

 * foreign keys:
    - game (n!:1)
    - type (---, n!:1)
 
## uo_goal

 * foreign keys
    - game (n!:1)
    - assist (uo_player, n?:1)
    - scorer (uo_player, n?:1)
 
## uo_image 

## uo_keys

 * foreign keys:
    - ???
 
## uo_license
 
 * foreign keys:
    - accreditation_id (???)
    - ???
 
## uo_location

## uo_location_info

 * foreign keys
    - location_id (n!:1)

## uo_moveteams

 * foreign keys
    - frompool (uo_pool, n!:1)
    - topool "
    - scheduling_id (1:1!)

## uo_movingtime

 * foreign keys
    - fromlocation (n!:1)
    - tolocation (n!:1)
     
    
## uo_pageload_counter

## uo_played

 * foreign keys
    - player (n!:1)
    - game (n!:1)
 
## uo_player

 * foreign keys
    - team (n?:1)?
    - accreditation_id (???)
    - profile_id (1:1!)?
 
## uo_player_profile

 * foreign keys
    - national_id (???)
    - accreditation_id (???)
    - ffindr_id (???)
 
## uo_player_stats

 * foreign keys
    - player_id (1!:1)?
    - profile_id (1!:1)?
    - team (n?:1)?
    - season (n?:1)?
    - series (n?:1)?
 
## uo_pool

 * foreign keys
    - series (n!:1)?
    - follower (1?:1?)

## uo_pooltemplate

## uo_registerrequest

 * foreign keys
    - userid (n!:1)

## uo_reservation

 * foreign keys
    - location (n!:1)
    - reservationgroup (--, n?:1)
    - season (n!:1)
 
## uo_scheduling_name

 * foreign keys
    - category (--, n?:1)
    - spiritpoints (--, n?:1)
 
## uo_season_stats

## uo_series

 * foreign keys
    - season (n!:1)
    - type (--)
 
## uo_series_stats

 * foreign keys
    - series_id (1!:1)
    - season (n!:1)
 
## uo_setting

## uo_sms

## uo_specialranking

 * foreign keys
    - frompool (n!:1)
    - scheduling_id (1:1!)

## uo_spirit

 * dropped

## uo_spirit_category

## uo_spirit_score

 * foreign keys
    - game_id (n!:1)
    - team_id (n!:1) 

 
## uo_team

 * foreign keys
    - pool (n?:1)?
    - club (n?:1)
    - series (n!:1)
    - country (n?:1)
 
## uo_team_pool

 * foreign keys
    - team (n!:m)
    - pool (n!:m)
 
## uo_team_profile

 * foreign keys
    - team_id(1!:1)
    - profile_image (???)
    - ffindr_id (???)
 
## uo_team_stats

 * foreign keys
    - team_id (1!:1)
    - season (n!:1)
    - series (n!:1)
 
## uo_timeout

 * foreign keys
    - game (n!:1)
    
## uo_translation

 * foreign keys
    - locale (--)
    - 
 
## uo_urls

 * foreign keys
    - owner (???)
    - owner_id (uo_player, n?:1)?
    - type (---, n?:1)
    - mediaowner (???)
    - publisher_id (???)
 
## uo_userproperties

 * foreign keys
    - user_id (n!:1)
    - value (---, n!:1)
 
## uo_users

## uo_extraemailrequest

 * foreign keys
    - userid (n!:1)
 
## uo_extraemail

 * foreign keys
    - userid (n!:1)

## uo_victorypoints

## uo_visitor_counter



 
 