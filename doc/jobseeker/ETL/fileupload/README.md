## File Upload (legacy)

File Upload has been replaced by [Data Assets](../data-assets). Data Assets combines registration and upload, works with Python, shell, Talend, and Docker jobs, and adds environment/job scoping, stable URIs, format metadata, checksums, and revisions.

Existing legacy input registrations are imported automatically; the old URL redirects to the new catalog.

### Home Screen
![Home](img/home.JPG)

### Uploading a file
Note that when you create an input component , it will appear in the Available Jobs dropdown Menu in the way you created.<br>
Once you fill in the form with the values created in the input component, it will prompt you to upload a file with the filename you created.<br>
You can drag and drop or click in the dropzone on the right.

![Upload](img/upload.JPG)

<br>

Uploading the file test.csv<br>
Once you get the success message, it means your file has been successfully uplodaed to the jobseeker repository folder according to your input component setup.<br>
For example in this case it will be: **jobseeker/repository/talend/input/dev/test.csv** <br>
So now you can call this file in your ETL job.

![Upload](img/uploaded.JPG)
