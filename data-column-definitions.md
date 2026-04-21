In the csv descriptions below the cells are identified by row#:col#


. . . . . . . .

National Mediabase Playlist Data Example

Data File Location:
data/NationalPlaylist_example.csv

this source is the Mediabase Published 7-Day Chart data for national tracking of songs' data.

Date and csv information is found at 2:1

The first set of column id start on row 4 of the csv.

4:1 - Rank
This is the national rank of a song.
Has a subset at cells
5:1 (LW - Last Week)
5:2 (TW - This Week)


4:4 - Artist
will need to account for differences in text case and how name is displayed between the different csv sources as there is not standardization between Mediabase, Luminate, or internally generated Music Master csv files. Decision making should rely more on the song title matching and the artist can be a 'close enough' matching check.

4:5 - Title
This is the song title, and is the first line of matching between csv sources. We must also account for discrepancies in text formatting as mentioned above for the artist name. In some cases the song title will be truncated in a source csv, or have punctuation like , . () " or - that may cause issues with matching songs across csv sources.

4:6 - Label
This is the record label owner of the song.

4:7 - Cancon
This column signifies if a song is Canadian Content.

4:8 - Spins
The amount of plays a song received nationally.
Spins has a subset at 
5:8 (TW - This Week)
5:9 (LW - Last Week)
5:10 (+/- - the movement up or down between Spins TW and LW)

4:11 - Dayparts
These are the spins by different periods of the day as defined by radio. There will be certain instances where we want to ignore the over night OVN time period.
Dayparts has a subset at
5:11 (OVN - Overnights)
5:12 (AMD - Mornings)
5:13 (MID - Middays)
5:14 (PMD - Afternoons)
5:15 (EVE - Evenings)

4:16 - Impressions
Has a subset at 
5:16 - TW (This Week)

4:17 - Stations
This is the number of stations who have played the song.
Stations has a subset at
5:17 (On - Number of stations songs was on)
5:18 (New - Number of station that just started playing the song)

4:19 - Avg. Station Rotations
This is the average number of plays and by how many stations.
Avg. Station Rotations as a subset at
5:19 (On - number of stations the song was played on.)
5:20 (TW - This Week's average play rotation)
5:21 (LW - Last Week's average play rotation)
5:22 (+/- - The movement between LW and TW)


. . . . . .

Station Mediabase Data File Example

Data File Location:
data/StationPlaylist_example.csv

This csv source is the Mediabase Station Playlist that tracks the data of our own stations' playing of songs, and also contains market specific data.

The date and csv information can be found at 2:1

Our first row of column id start at 4:1
The first row of data is row 6

4:1 - Station Rank
The Station Rank column shows the rank of the song on the station for LW and TW. The default sort for the report is Station Rank TW.
Has a subset at cells 
5:1 (LW - Last Week)
5:2 (TW - This Week)


4:3 - Artist
This column will need to account for differences in text case and how name is displayed between the different csv sources as there is not standardization between Mediabase, Luminate, or internally generated csv files. Decision making should rely more on the song title matching and the artist can be a 'close enough' matching check.

4:4 - Title
The song title is the first line of matching between csv sources. We
must also account for discrepancies in text formatting as mentioned above for the artist name. in some cases the song title will be truncated in the source csv, or have punctuation like , () . " or - that may cause issues with matching songs across csv sources.

4:5 - Cancon
This column signifies if a song is Canadian Content.

4:6 - Label
This is the record label owner of the song.

4:7 - Year
This is the year the song was released.

4:8 - Spins
The amount of plays a song received nationally.
Spins has a subset at 
5:8 (TW - This Week)
5:9 (LW - Last Week)
5:10 (+/- - the movement up or down between Spins TW and LW)

4:11 - Dayparts
These are the spins by different periods of the day as defined by radio programming. There will be certain instances where we want to ignore the over night OVN time period.
Dayparts has a subset at
5:11 (OVN - Overnights)
5:12 (AMD - Mornings)
5:13 (MID - Middays)
5:14 (PMD - Afternoons)
5:15 (EVE - Evenings)

4:16 - Market Info
This column shows the station's market share of spins.
Market Info has a subset at
5:16 ([StationCallLetters] Share(%) - the station's market share percentage of spins)
5:17 (Market Spins TW - this week's spins in the station's market)

4:18 - Impressions
These are the impressions by million.
Impressions has a subset at
5:18 (Reach/Mill - the reach per million)
5:19 (Imp Rank - the station's rank by impressions)

4:20 - Historical Data Since: mm/dd/yyyy
This is the first recorded date the song was first played by the station and has a subset at
5:20 (First Played - the date of the first time played, and note the date format varies from yyyy-mm-dd and mm/dd/yy)
5:21 (Hist Spins - the historical number of spins)

4:22 - Format Comparison
The Format Comparison columns show the songs Rank on the format chart corresponding to the selected station. The Format Average (AVG) is the average number of spins the song is receiving across all stations within the selected stations format. The column with the station's call letters shows the over/under for spins TW on the selected station compared to the format average.
Format Comparison has a subset at
5:22 (Rank - see above)
5:23 (Avg - see above)
5:24 ([station] - see above)

4:25 - 6am to 7pm
The 6AM to 7AM columns show the rank of the song and the spins for TW for the hours between 6AM and 7PM.
it has a subset at
5:25 (Rank - see above)
5:26 (TW - see above)

4:27 - W/O OVN
The W/O OVN columns show the airplay for all dayparts except for Overnights (12am-6am).
it has a subset at
5:27 (Rank - see above)
5:28 (TW - see above)


. . . . . . .


CIWV Final Output and Default Module Table Layout

Data File Location:
data/final-output-example.csv

This is the important final output goal, and we are using CIWV as our example. The final product we want to be able to generate from our national and local Mediabase csv sources combined with the station's own Music Master csv reporting, and adding the Luminate streaming data csv.

The dates and csv info are found at cell 1:1

At 2:1 we start our category options (dropdown option selection as noted at the beginning when discussing the Category TW and NW options).
Note in this example of CIWV we only have 1 category column but our final product should have 2 columns used so that reviewers can see the intended changes to a song that's being set for next week. for now ignore that this is missing in our final-output-example.csv

2:2 - ARTIST
This is the resulting artist name after combining our csv files via upload options.

2:3 - TITLE
This is the resulting song title after combining our csv files via upload options.

2:4 - WEEKS
The number of weeks the song has been on the station's playlist receiving spins.

2:5 - CAT
Make this to be CAT/CODE and provide the following user selectable options, similar to how our Category TW/NW setup functions. These options however do not need to be compared and updated week over week. What ever the user sets them to during their dashboard routine will be saved and included in the exported final output file. If no options is selected for a song, then leave the cell data empty or use a - if need be.

Options in the dropdown should be:
1
2
3
S
PSG
G
F
GS
GP
P
V
T
TG

2:6 - Spins ATD
This is the station's number of spins taken from the Station Playlist csv file from the column - Hist Spins.

2:7 - #Streams CA
This is the total number of streams nationally, taken from the Luminate csv streaming data.

2:8 - #Streams Van
This is the total number of streams in Vancouver, BC, taken from the market-streaming-data-example.csv Luminate csv streaming data.

2:9 - #Spins TW
The number of spins in the station's market this week.

2:10 - #Stns TW
The number of stations in our statin's market that played the song.

2:11 - Avg Spins
The average number of spins per station in our market, meaning cell 2:9 divided by cell 2:10.

2:12 - MB Cht
(leave this column's data empty for now until we learn more)

2:13 - Rk
The rank as found in the station playlist csv file under 5:22, the Rank under Format Comparison.

2:14 - Peak
I don't currently understand how this is calculated from our source csv files, but it is the peak of where the station has ranked.

2:15- BB SJ Chart
Leave empty for now, as we don't currently have this dataset. It will need an option to be uploaded to our set of csv files, but can be blank if no data is found.

2:16 - Freq/Listen ATD
Leave empty for now until we learn more. I do not know how this data is calculated between our source files.

2:17 - Impres ATD
Leave empty until we learn more. I don not know how this data is calculated.


. . . . . . .


LUMINATE STATION STREAMING DATA CSV EXAMPLE

Data File Location:
data/StreamingDataStation_example.csv

This dataset has 6 row entries for every song. They are different as noted by the 1:7 Activity column as follows and in this order:
Airplay Spins (current week)
Airplay Spins (last week)
Airplay Audience (current week)
Airplay Audience (last week)
Streams (current week)
Streams (last week)


1:1 - Location
this column can be ignored

1:2 - Title
the title of the song, which as noted in our previous instructions needs to account for different text casing used, being truncated, or having punctuation, commas, hyphens, brackets, etc.

1:3 - Artist
The name of the artist that can be helpful in matching the data between csv source files.

1:4 - Release Date
The release date of the song.

1:5 - Imprint Label
This column can be ignored

1:6 - Luminate ID
this column can be ignored

1:7 - Activity
This is the type of spins being reported by this csv source file:
Airplay Spins (current week)
Airplay Spins (last week)
Airplay Audience (current week)
Airplay Audience (last week)
Streams (current week)
Streams (last week)

1:8 - Week
the number denoting the current week and the previous week.

1:9 - Year
This column can either be ignored, or be used.

1:10 - Date
this is the date start of the week being reported.

1:11 - Rank
the stations rank for airplay spins in this reporting.

1:12 - Quantity
the number of streams being reported for a song.

1:13 - % Change
the change between what's being reported week over week as per the two weeks indicated in 1:8.


. . . . . . .


Market-Based Streaming Luminate Data csv

This csv source shows airplay and streaming data by market. In this case it is Vancouver, BC for our CIWV build example.

Example DATA File Location:
data/StreamingDataMarket_example.csv

1:1 - Location
This is the country column and can be ignored.

1:2 - Market
This column is used to indicate which rows contain market-based streaming data.

1:3 - Title
the title of the song, which as noted in our previous instructions needs to account for different text casing used, being truncated, or having punctuation, commas, hyphens, brackets, etc.

1:4 - Artist
The name of the artist that can be helpful in matching the data between csv source files.

1:5 - Release Date
The release date of the song.

1:6 - Imprint Label
This column can be ignored

1:7 - Luminate ID
this column can be ignored

1:8 - Activity
Remember that each song has these 8 row entries in this order.
This is the type of spins being reported by this csv source file and relevant to column 1:9 which are the current week and last week:
Airplay Spins (current week)
Airplay Spins (last week)
Airplay Audience (current week)
Airplay Audience (last week)
Streams (current week)
Streams (last week)
Streams (current week's market-based streaming data)
Streams (last week's market-based streaming data)

1:9 - Week
the number denoting the current week and the previous week.

1:10 - Year
This column can either be ignored, or be used.

1:11 - Date
this is the date start and end of the week being reported on that row.

1:12 - Rank
the song ranking in this market's airplay and audience reporting.

1:13 - Quantity
the number of spins and streams being reported for a song.

1:14 - % Change
the change between what's being reported week over week as per the two weeks indicated in 1:9.


. . . . . . .


Music Master CSV File

Location: data/MusicMasterCSV_example.csv

2:1 - Cat.
This column denotes the TW category selection and should change this option in our dashboard automatically when uploaded.

2:2 - Artist
This will need to be matched up with the artists already in the dashboard from the other csv sources.

2:3 - Title
This will need to be matched to the Title/Artist in the main dashboard when uploaded.

2:4 - WKS
The number of weeks the song has been in rotation on the station.

2:5 - [empty column title]
This data should be injected into our module table based on our template (data/final-output-example.csv) at cell 2:5- CAT.
As mention in the column description for data/final-output-example.csv, these column should be drop down selector with ascribed options, and should update automatcially when this Music Master CSV file is uploaded.

2:6 - SPINS
The number of spins as reported by the station's Music Master system, for that week.

. . . . .  .

Billboard Chart Example Data

Location: data/BillboardChart_example.csv

not currently used but needs to be planned for as a final source to be injected into our module table as per the above output example csv.



