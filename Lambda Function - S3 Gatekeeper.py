from __future__ import print_function   #allows using print(str()) to log anything in CloudWatch
import requests
# import json
import boto3
import hashlib
import hmac
import pymysql
import sys
import string
import random

secret = 'xxxxxxxx'
rds_host  = ""
rds_user = ""
rds_pw = ""
db_name = ""

s3 = boto3.client('s3')

def hmac256(seckey, data):
    return hmac.new(seckey, data, hashlib.sha256).hexdigest()

def lambda_handler(event, context):
    
    bucket = event['Records'][0]['s3']['bucket']['name']
    key = event['Records'][0]['s3']['object']['key']
    size = event['Records'][0]['s3']['object']['size']
    
    # ------------------------------------------------ #
    
    obj = s3.head_object(Bucket=bucket, Key=key)
    meta = obj['Metadata']
    
    if meta is None:
        print(str('no metadata in object'))
        return
    
    meta_name = meta['name']
    meta_user = meta['user']
    meta_size = meta['size']
    meta_parent = meta['parent']
    meta_time = meta['time']
    meta_signature = meta['signature']
    
    if size != int(meta_size):
        print(str('size mismatch'))
        return
    
    print(str(meta))
    
    # ------------------------------------------------ #
    
    key_split = key.split('/')
    user_id = key_split[0]
    guid = key_split[1]
    
    if user_id != meta_user:
        print(str('user_id mismatch'))
        return
    
    hash_string = str(user_id) + str(size) + str(meta_parent) + guid + meta_time + secret
    signature = hmac256(bytes(secret, 'utf-8'), bytes(hash_string, 'utf-8'))
    
    if signature != meta_signature:
        print(str('signature mismatch'))
        return
    
    print(str(signature))
    
    # ------------------------------------------------ #
    
    file_type = obj['ContentType']
    file_id = ''.join(random.SystemRandom().choice(string.ascii_letters + string.digits) for _ in range(33))

    conn = pymysql.connect(host=rds_host, user=rds_user, password=rds_pw, db=db_name, connect_timeout=3)
    with conn.cursor() as cur:
        query = "INSERT INTO files (name, type, size, user_id, parent_id, file_id, guid) VALUES( '%s', '%s', %s, %s, %s, '%s', '%s' ) "
        query = query + "ON DUPLICATE KEY UPDATE "
        query = query + "name = CASE WHEN user_id = VALUES(user_id) THEN VALUES(name) ELSE name END,"
        query = query + "type = CASE WHEN user_id = VALUES(user_id) THEN VALUES(type) ELSE type END,"
        query = query + "size = CASE WHEN user_id = VALUES(user_id) THEN VALUES(size) ELSE size END,"
        query = query + "guid = CASE WHEN user_id = VALUES(user_id) THEN VALUES(guid) ELSE guid END,"
        query = query + "file_id = CASE WHEN user_id = VALUES(user_id) THEN VALUES(file_id) ELSE file_id END,"
        query = query + "created_at = CASE WHEN user_id = VALUES(user_id) THEN NOW() ELSE created_at END,"
        query = query + "updated_at = CASE WHEN user_id = VALUES(user_id) THEN NOW() ELSE updated_at END;"
        
        cur.execute(query % (meta_name,  file_type,  int(size),  int(user_id),  int(meta_parent),  file_id,  guid))
        conn.commit()
        cur.close()