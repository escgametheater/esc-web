import React from 'react';
import {
  Button,
} from "mineral-ui";


export default class FileUploadButton extends React.PureComponent {
  triggerFileUpload = e => {
    if (this.refs.fileInput) {
      this.refs.fileInput.click();
    }
  };

  render() {
    return (
      <React.Fragment>
        <input
          type="file"
          accept={this.props.accept}
          onChange={this.props.onChange}
          style={{ visibility: "hidden", position: "fixed" }}
          ref="fileInput"
        />
        <Button onClick={this.triggerFileUpload}>{this.props.children}</Button>
      </React.Fragment>
    );
  }
}
