import sys
import time
from os import path

import pandas as pd
from jobseeker import JobSeeker


JOB_NAME = path.basename(__file__).replace(".py", "")
ENVIRONMENT = sys.argv[1] if len(sys.argv) > 1 else "LOCAL"


def build_employee_dataframe():
    return pd.DataFrame(
        {
            "Name": ["Jai", "Princi", "Gaurav", "Anuj", "Geeku", "Matheus", "Beatriz", "Natalia", "Carlos", "Vanessa"],
            "Age": [27, 24, 22, 32, 15, 25, 24, 31, 44, 39],
            "Address": ["Delhi", "Kanpur", "Allahabad", "Kannauj", "Noida", "Areao", "Nogueira", "Ipanema", "Areao", "Marli"],
            "Qualification": ["Msc", "MA", "MCA", "Phd", "10th", "Msc", "Msc", "Bsc", "Phd", "MBA"],
        }
    )


def operation():
    with JobSeeker(environment=ENVIRONMENT, job=JOB_NAME) as js:
        with js.task("Sample Python Job", "DW_Master") as tmf:
            rows = tmf.context("rows", cast=int, default=10)
            df = build_employee_dataframe()
            output = df.head(rows)

            print(output)
            print("Sleeping {} seconds.".format(rows))
            time.sleep(rows)

            html = output.to_html(index=False)
            message = "<h4>This is a HTML table generated from python pandas</h4><br>" + html
            tmf.finish(total=rows, processed=len(output.index), msg=message)

    return True


if __name__ == "__main__":
    operation()
