#include <stdio.h>
#include <stdlib.h>
#include <pthread.h>
#include <unistd.h>
 
// A normal C function that is executed as a thread 
// when its name is specified in pthread_create()
void *myThreadFun(void *vargp)
{
    system("php runner.php");
    pthread_exit(0);
    return NULL;
}
  
int main()
{
    pthread_t thread_id;
    while(1) {
        pthread_create(&thread_id, NULL, myThreadFun, NULL);
        pthread_join(thread_id, NULL);
        int timeout = 10;
        const char buffer[] = "waiting %d seconds\r";
        char result[19];
        while(--timeout > 0) {
            sprintf(result, buffer, timeout);
            write(1, result, 19);
            sleep(1);
        }
        printf("\n");
    }
    exit(0);
}
