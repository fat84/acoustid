# AcoustId API Wrapper
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

This package is an object oriented PHP wrapper for [acoustid.org API](https://acoustid.org/webservice).

## Installation

Just require this package using [Composer](https://getcomposer.org/):
```bash
composer require mrfeathers/acoustid
```

## Usage

Create an instance of `AcoustIdClient`:
```php
$apiKey = 'here is your api key';
$acoustidClient = AcoustidFactory::create($apiKey);
```
>Also, you can create client without api key. In this case, you are able to use `listByMBId` method, that
doesn't need api key. All other methods throws an exception in this case.

**Now you're ready!**

So, acoustid.org has some available actions, that are represented in the package client:
 
 - **Lookup by fingerprint**
 
 If you have an audio fingerprint generated by [Chromaprint](https://acoustid.org/chromaprint), you can use this method to lookup the 
 MusicBrainz metadata associated with this fingerprint. Use class [`FingerPrint`](/doc/Fingerprint.md) for this method.
 ```php
 $fingerPrintContent = 'fingerprint string generated by Chromaprint';
 $fingerPrintDuration = 100;
 
 //meta can be empty, it's not required
 $meta = [
    Meta::RECORDINGS,
    Meta::RELEASES,
 ];
 
 $fingerPrint = new FingerPrint($fingerPrintContent, $fingerPrintDuration);
 $resultCollection = $acoustidClient->lookupByFingerPrint($fingerPrint, $meta);
 ```
 You will get an instance of class `ResultCollection`, contains several (or one) objects of `Result` class. 
 `ResultCollection` is iterable, so you can just use it as array:
 ```php
 foreach($resultCollection as $result) {
    //do something
 }
 ```
 - **Lookup by track id**
 
 You can also look up data connected to a track ID, witch is a cluster of fingerprints.
 ```php
  //track id is a simple uuid string
  $trackId = '08268577-90f3-4ed5-8154-5768e2091554';
  //meta can be empty, it's not required
  $meta = [
     Meta::RECORDINGS,
     Meta::RELEASES,
  ];
  
  $resultCollection = $acoustidClient->lookupByTrackId($trackId, $meta);
 ```
 The result also will be `ResultCollection`.
 - **Submit fingerprint**
 
 The AcoustID database depends on user-submitted content. This method can be used to submit new audio 
 fingerprints and their corresponding metadata to the database. Multiple fingerprints can be submitted in one call.
 
 Submissions are processed asynchronously by a background job. However, this normally only takes a few 
 seconds, so if your application needs to get the imported AcoustIDs back, you can use the wait parameter 
 to `wait` until the submissions are imported. It is not guaranteed that the submissions will be imported 
 on time, so your application must be able to handle the case when the submission is still in the 
 "pending" status. You can look up the status of the pending submissions later (use method `getSubmissionStatus`).
 
 While you can submit a fingerprint without any metadata, it is not very useful to do so. If the file 
 has embedded MusicBrainz tags, please send the MusicBrainz recording ID. Otherwise you can send the 
 textual metadata.
 
 ```php
 //create collection of fingerprints
 $fingerPrintCollection = new FingerPrintCollection([
    new FingerPrint('fingerprint string generated by Chromaprint', 100),
    new FingerPrint('fingerprint string generated by Chromaprint', 121),
 ]);
 
 //also you can add FingerPrint later by addFingerPrint method
 $fingerPrint = new FingerPrint('....', 200);
 
 //FingerPrint object has a lot of additional fields, that you can set calling setters
 $fingerPrint->setArtist('artist name')
    ->setAlbum('album name');
 
 $fingerPrintCollection->addFingerPrint($fingerPrint);
 
 $userApiKey = 'here is your personal user key';
 //wait is not required and equls 1 by default. Use it if you need to wait until the submissions are imported
 $wait = 1;
 
 $submissionCollection = $acoustidClient->submit($fingerPrintCollection, $userApiKey, $wait);
 ```
 You will get an instance of `SubmissionCollection` as a result. It's also iterable and contains one or more `Submission` objects.
- **Get submission status** 

If you submitted an fingerprint and it was imported immediately, you can check the status of the submission 
by looking up the ID from the `submit` call.
```php
//let's get submisstionCollection from the previous example
$submission = array_shift($submissionCollection);

$result = $acoustidClient->getSubmissionStatus($submission->getId());
```
The result also will be an instance of `SubmissionCollection`.
- **List AcoustIDs by MBID**

This method will return you a list of AcoustId id that are corresponding to MusicBrainz id.

>Api key is not required for this method, so you can use client without key to call it

You can send one or more mbids. If you send one, than you'll get the result as instance of `TrackCollection`.
If you send more than one - instance of `MBIdCollection`. Both are iterable.
```php
$mbids = ['83b3d37d-8c3d-4a7b-ae9a-d635e4e3374f'];

$trackCollection = $acoustidClient->listByMBId($mbids);

//add one more mbid
$mbids[] = 'b00db402-51d2-4125-912b-c0eab9c6edd1';

//and you'll get MBIdCollection, each MBId object for each sent mbid
$mbidCollection = $acoustidClient->listByMBId($mbids);

```

## Response models and Meta

Response models contain all known fields acoustid.org API can return. Unfortunately, acoustid.org doesn't have a full documentation
for all possible response structures. So if you get an exception with message `Can't deserialize response`, please [open an issue](https://github.com/MrFeathers/acoustid/issues/new) and provide
method and parameters you used to call it.

Here's the descriptions of all response models:
- [Artist](doc/Artist.md)
- Date
- MBId
- [Medium](doc/Medium.md)
- Recording
- Release
- ReleaseEvent
- Result
- Submission
- [Track](doc/Track.md)

Collection response models:
- MBIdCollection
- ResultCollection
- SubmissionCollection
- TrackCollection

>**NB!** Response models has a lot of fields, but some of them can be empty. It's because of scope of meta parameters you send to the method.
Different scope of meta values can return different results. All available meta values you can find in the class `Meta`. It's highly recommended 
to use constants from `Meta` class for creating meta array.
