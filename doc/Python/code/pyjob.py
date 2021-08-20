from script.jobseeker import jobSeeker
import sys
from os import path
import pandas as pd

# Declaring job name as file name
jobName = path.basename(__file__).replace(".py","")

# Checking paramenter pos 1 as environment
if len(sys.argv) <= 1:
    environment = "LOCAL"
else:
    environment = sys.argv[1]

# Starting Jobseeker script
js = jobSeeker(environment,jobName)

# Querying context Custom
rows = int(js.getContext("rows"))

# Declaring Operation Function
def operation():
    try:
        # Jobseeker Start Transaction
        js.begin("Sample Python Job","DW_Master")

        # Define a dictionary containing employee data
        data = {'Name':['Jai', 'Princi', 'Gaurav', 'Anuj', 'Geeku','Matheus','Beatriz','Natalia','Carlos','Vanessa'],
                'Age':[27, 24, 22, 32, 15,25,24,31,44,39],
                'Address':['Delhi', 'Kanpur', 'Allahabad', 'Kannauj', 'Noida','Areão','Nogueira','Ipanema','Areão','Marli'],
                'Qualification':['Msc', 'MA', 'MCA', 'Phd', '10th','Msc','Msc','Bsc','Phd','MBA']}
        
        # Convert the dictionary into DataFrame
        df = pd.DataFrame(data)
        
        # Print Dataframe to console
        print(df.head(rows))
        
        # Convert to HTML Table
        html = df.to_html()
        msg = "<h4>This is a HTML table generated from python pandas</h4><br>"+html

        # Jobseeker End Transaction
        js.end(rows,rows,msg)
        return True

    except Exception as e:
        js.error(str(e))
        return False

# Calling Operation function
operation()


