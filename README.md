# PMID

**Author:** Thomas Steimlé

## :speech_balloon: Description

[MediaWiki](https://www.mediawiki.org/) Extension for rendering medical reference by parsing [PubMed](https://pubmed.ncbi.nlm.nih.gov/) database.


## :sparkles: Install

```
cd ${MediaWikiROOT}/extensions/
git clone https://github.com/Dr-TSteimle/PMID.git
```

Add the following line at the end o`⅞f ${MediaWikiROOT}/LocalSettings.php : 

```
require_once("$IP/extensions/PMID/pmid.php");
```

## :+1: Thanks

Thanks to @asifr for its PubMed API client class [PHP-PubMed-API-Wrapper](https://github.com/asifr/PHP-PubMed-API-Wrapper)

## Copyright and License

This script is free software, available under the terms of the BSD-style open source license reproduced below, or, at your option, under the [GNU General Public License version 2](http://www.gnu.org/licenses/gpl-2.0.txt) or a later version.

PMID
Copyright © 2016 Thomas Steimlé
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.

Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.

Neither the name "PHP PubMedAPI Wrapper" nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.

This software is provided by the copyright holders and contributors "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the copyright owner or contributors be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.

PHP PubMed API Wrapper  
Copyright © 2012 Asif Rahman  
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.

Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.

Neither the name "PHP PubMedAPI Wrapper" nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.

This software is provided by the copyright holders and contributors "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the copyright owner or contributors be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.