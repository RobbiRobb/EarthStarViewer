## Overview

### Introduction
Comic Earth Star is a Japanese manga magazine. On their website they offer a glimpse into a lot of series by providing the first and the newest chapter for free. The chapters are available through a special manga reader, where they can be read as long as you have the ID to a chapter. And since chapters don't get deleted, but just aren't linked anymore, as long as you know where to look, you can still read them. In short, this project reverse engineered said manga reader to allow downloading of the images used to display the different pages.

### Basics
The reader is available via [this URL](https://viewer.comic-earthstar.jp/viewer.html) but won't work without an ID. Provided with an ID, the reader will load the pages, like [this](https://viewer.comic-earthstar.jp/viewer.html?cid=fc221309746013ac554571fbd180e1c8&cty=1&lin=0). The IDs themselves are just [MD5 hash values](https://en.wikipedia.org/wiki/MD5) of numbers, starting from 0. The lower numbers are made up of a lot of unused numbers but in higher numbers thise holes will shrink. At the point of publishing this README (Feb. 2023) the numbers are just below 3000.

### Page load trace
When the viewer is first laoded it will make a call to the API, checking if it can find a series with the given ID. This can in reverse also be used to look for all existing series in the database. The API call looks like [this](https://api.comic-earthstar.jp/c.php?cid=fc221309746013ac554571fbd180e1c8), where the ID is the same MD5 hash value that is also used to identify the chapter. The API returns a URL which can then be used to load the [configuration file](https://storage.comic-earthstar.jp/data/yamanosusume/yamanosusume01/configuration_pack.json) for the manga chapter. This provides an overview over the contents of the chapter and details on every page. The base URL in combination with the details of the page can then be used to load the [actual image](https://storage.comic-earthstar.jp/data/yamanosusume/yamanosusume01/item/xhtml/p-016.xhtml/0.jpeg) of the page. However, as should be obvious, the image is totally scrambled and in no way readable.

### Image reconstruction
While the image file itself is totally scrambled, the result in the viewer isn't, so obviously the viewer is able to reverse the image scrambling processs that was applied before the image was uploaded. And since the viewer is completely written in JavaScript it is pretty easy to search through the [source code](https://viewer.comic-earthstar.jp/js/viewer_1.0.1_2017-01-16.js) and find the relevant parts. This project uses said parts to reverse the scrambling and directly save the image to the hard drive in its original resolution.

## Usage
To use this project to download one or multiple chapters simply include the file, set up an instance with a hash value and download the chapter:

```php
require_once("EarthStarViewer/earthstarviewer.php");

$viewer = new EarthStarViewer("fc221309746013ac554571fbd180e1c8");
try {
	$viewer->download();
} catch(Exception $e) {
	var_dump(json_decode($e->getMessage()));
}
```

Be aware that an error will be thrown if the chapter can't be downloaded. See errors below for an explanation.

### Errors
#### 400
If the ID is not valid and no chapter can be found in the database, an error with the ID 400 will be returned.

#### 404
This error will be returned if either the configuration file could not be loaded even though the API returns a URL for the given ID or if the URL in itself is not valid and doesn't point to a directory that could contain a configuration file. In both cases the chapter can not be downloaded.