# Jobseeker Framework log monitoring and task scheduler python class

import mysql.connector #pip install mysql-connector-python
import sys
import random
import string
import socket
import getpass

class jobSeeker:
    
    # JobSeeker MySQL(MariaDB) database 
    connection = {
        "host": "192.168.0.6",
        "port": 3306,
        "user": "python",
        "password": "python",
        "database": "jobseeker"
    }   
    
    # Declaring environment and opening connection with mariadb
    def __init__(self, environment="LOCAL",job=None):
        
        # Start
        self.environment = environment
        self.job = job
        print("Job Name: ", job)
        print("Context Environment: ", environment)
        
        try:
            # Opening MySQL DB Conneciong
            mydb = mysql.connector.connect(
                  host=self.connection["host"],
                  user=self.connection["user"],
                  password=self.connection["password"],
                  database=self.connection["database"]) 
            
            # Storing MySQL DB Connection
            self.mydb = mydb
            
            # Generating Instance ID
            letters = string.ascii_uppercase
            id = ''.join(random.choice(letters) for i in range(8))
            self.id = id
            
        except Exception as e:
            print(f"Error connecting to MariaDB Platform: {e}")
            sys.exit(1)
    
    # Get Context method
    def getContext(self,context):
        try:
            # Query from contextdetails view
            mycursor = self.mydb.cursor()
            mycursor.execute("SELECT ContextValue FROM vw_contextdetails WHERE Contextkey = '%s' and Environment = '%s' " % (context,self.environment))
            query = mycursor.fetchall()

            # Check if the result was found on database
            if(mycursor.rowcount == 1):
                query = query[0][0]
            else:
                query = "Context provided was not found on database, please check your context parameter."

            mycursor.close
            return query
        
        except Exception as e:
            print(f"Error connecting to MariaDB Platform, error to retrieve context: {e}")
    
    def begin(self,eventText="",dimension=""):
        try:
            # Insert into tmf begin tmf transaction
            mycursor = self.mydb.cursor()
            sql = "INSERT INTO tmf (interface_id,STATUS,job_name,reprocess,event_text,DIMENSION,environment,records_total,records_processed,last_activity,running_time,distict_errors,WARNINGS,hostname,username,instance_id,start_time,msg) \
                   VALUES ('1','running',%s, 0, %s, %s, %s,0,0,now(),NULL,NULL,NULL,%s,%s,%s, now(), NULL)"
            values = self.job,eventText,dimension,self.environment,socket.gethostname(),getpass.getuser(),self.id
            mycursor.execute(sql,values)
            
            # Check if the record has been inserted
            if(mycursor.rowcount == 1):
                print("[Transaction Started] ",mycursor.rowcount, "record inserted.")
                self.mydb.commit()
                return True
            else:
                print("Some error has occured, no record has been inserted, attempting to rollback operation.")
                self.mydb.rollback()
                return False
            mycursor.close
            
        except Exception as e:
            print(f"Error connecting to MariaDB Platform, error to begin tmf transaction: {e}")
            
    
    def end(self,records_total=0,records_processed=0,msg=""):
        try:
            # update tmf begin tmf transaction to end transaction
            mycursor = self.mydb.cursor()
            sql = "UPDATE tmf SET status = 'ready', records_total = %s , records_processed = %s , last_activity = now(),  msg = %s WHERE instance_id = %s AND environment = %s AND job_name = %s"
            values = records_total,records_processed,msg,self.id,self.environment,self.job
            mycursor.execute(sql,values)
            
            # Check if the record has been inserted
            if(mycursor.rowcount == 1):
                print("[Transaction Finished] ",mycursor.rowcount, "record inserted.")
                self.mydb.commit()
                return True
            else:
                print("Some error has occured, no record has been updated, attempting to rollback operation.")
                self.mydb.rollback()
                return False
            mycursor.close
                  
        except Exception as e:
            print(f"Error connecting to MariaDB Platform, error to end tmf transaction: {e}")

    def error(self,msg="",origin="Python Method",code=1):
        try:
            mycursor = self.mydb.cursor()
            
            # Insert error into tmf_error table
            sql = "INSERT INTO tmf_error (tmf_id,job_name,moment,type,origin,message,code) VALUES (%s, %s, now(), 'Python Exception', %s, %s , %s)"
            values = self.id,self.job,origin,msg,code
            
            # Update tmf table with error
            sql2 = "UPDATE tmf SET status = 'error', event_text = 'Error(s) Found' , last_activity = now(), distict_errors = 1, warnings = '0' WHERE instance_id = %s AND job_name = %s and environment = %s"
            values2 = self.id,self.job,self.environment
            
            # Run queries
            mycursor.execute(sql,values)
            mycursor.execute(sql2,values2)
            
            # Check if the record has been inserted
            if(mycursor.rowcount == 1):
                print("[Transaction Error] ",mycursor.rowcount, "record inserted.")
                self.mydb.commit()
                return True
            else:
                print("Some error has occured, no record has been updated, attempting to rollback operation.")
                self.mydb.rollback()
                return False
            mycursor.close
        
        except Exception as e:
            print(f"Error connecting to MariaDB Platform, error to error tmf transaction: {e}")
        

        
        