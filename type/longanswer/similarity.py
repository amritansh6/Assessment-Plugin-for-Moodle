print("fhuif")
import requests
# import json
url = 'https://api.dandelion.eu/datatxt/sim/v1/?text1=Cameron%20wins%20the%20Oscar&text2=All%20nominees%20for%20the%20Academy%20Awards&token=ede1e3957db349e99e82965db2d0b897'

r = requests.post(url).json()
print(r["similarity"])