ILIAS Extended Test Statistics plugin
=====================================

Copyright (c) 2017 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv2, see LICENSE

- Authors:   Fred Neumann <fred.neumann@ili.fau.de>, Jesus Copado <jesus.copado@ili.fau.de>


Installation
------------

When you download the Plugin as ZIP file from GitHub, please rename the extracted directory to *ExtendedTestStatistics*
(remove the branch suffix, e.g. -master).

1. Copy the ExtendedTestStatistics directory to your ILIAS installation at the followin path
(create subdirectories, if neccessary): Customizing/global/plugins/Services/UIComponent/UserInterfaceHook
2. Go to Administration > Plugins
3. Choose action  "Update" for the ExtendedTestStatistics plugin
4. Choose action  "Activate" for the ExtendedTestStatistics plugin

Configuration
-------------

5. Choose action "Configure" 
6. Select which evaluations should be presented to all users with access to the test statistics,
   which should only be shown to platform admins and which should be hidden at all.
7. 

Usage
-----
This plugin replaces the sub tab "Aggregated Test Results" of the "Statistics" tab in a test 
with two new sub tabs "Aggregated Test Results" and "Aggregated Question Results"

Both tabs show tables with statistical figures related to the test or its question. The values that are
calculated by standard ILIAS are always shown. Other values are shown based on the setting in the plugin 
configuration.

Additional test evaluations:
* Coefficient of Internal Consistency (Cronbach's alpha)
* Mean Score
* Median Score
* Standard Deviation

Additional question evaluations:
* Discrimination index
* Facility Index
* List of chosen options for single and multiple choice
* Standard deviation

The evaluation titles have tooltips with further explanations. Some values have read/yellow/green signs to indicate their quality. 
Values displayed in italics are considered as 'uncertain' because they are calculated in a random test. Nevertheless they 
may provide useful hints. Values may also have comments appearing as tooltips.

The toolbar above the tables allows to select of they are calculated for the scored pass (standard), the best pass or the last pass of each 
participant.

Export
------
The aggregated results can be exported to CSV or MS Excel files. A CSV file contains only one table with either test or questions overview
and includes no comments or quality signs. An MS Excel file contains both tables on separate sheets with an extra sheet for the legend. It
includes all comments and color bachgrounds for the differend quality signs. An MS Excel file with details contains an extra sheet for any
detailed evaluation.


Known Issues
------------
* The extended statistics are currently not calculated for tests with setting 'Question Queue - All Questions from a Question Pool'.
  This, however, is also the case for the standard statistics of ILIAS.

* The calculation of extended evaluations takes care of performance, e.g. the additional question evaluations are calculated on a separate
  page only for the questions that are shown. But the basic values from ILIAS are calculated for all, which takes time for large tests.
  In this case it's better to create an MS Excel export that is stored on the 'export' tab. 


Version History
===============

Version 1.0.0 (2017-03-20)
-------------
- Support ILIAS 5.0 to ILIAS 5.2
- Improved Excel export
- Improved quality signs
- Configuration parameters for evaluations
- Selection of evaluated pass (scored, last, best)
- Added 'uncertain' status for calculations in random tests


Version 0.9.0
-------------
First beta version for community testing.