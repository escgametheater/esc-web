import React, { PureComponent } from "react";
import { Button, Box, Card, CardBlock, Text, Table, Link } from "mineral-ui";

import moment from "moment";
import numeral from "numeral";
import { getUrl } from "../api";
import FileUploadButton from "../components/FileUploadButton";

const gameContentMessages = {
  title: "Live Game Custom Questions",
  description:
    "Add your own questions to show on the big screen during live Fan Activations."
};

// Map of column definitions to keys in data
const columns = [
  { content: "Key", key: "key" },
  { content: "# of questions", key: "questionsCount" }
  // { content: 'File size', key: 'size' },
];

// Transform data by column key before rendering
const columnTransformer = {
  lastModified: n => moment(n).format("MMM Do, YYYY"),
  size: n => numeral(n).format("0b")
};

export default class Content extends PureComponent {
  mapDataToHumanReadable = (data = [], fields) =>
    data.map(d => {
      const mutD = { ...d };
      Object.keys(d).forEach(k => {
        const transformer = columnTransformer[k];
        if (transformer) {
          mutD[k] = transformer(mutD[k]);
        }
      });

      mutD["questionsCount"] =
        d["game_mod_data_sheets"][0].game_mod_data_sheet_rows.length;

      // @todo make generic
      const fieldKey = "questionDataSet";
      const field = fields.find(f => f.name === `custom-data-${fieldKey}`);

      if (field && field.changed) {
        mutD["questionsCount"] = "Count calculated on save";
      } 

      return mutD;
    });

  handleFileChange = e => {
    const file = new Blob([e.target.files[0]]);

    // Right type?
    // file.type ...

    // Right size?
    // if (file.size >= 128000) {
    // console.error("To big!", file.size);
    // return;
    // }

    // @todo generic
    // const questionDataSet = this.props.gameModData.find(
    //   gmd => gmd.key === "questionDataSet"
    // );


    // if (!questionDataSet) {
    //   console.error(`Key doesn't exist, assuming "questionDataSet"`);
    // }

    // @todo make generic
    const key = "questionDataSet";
    this.props.onCustomDataChange(key, file);
  };

  render() {
    const { title, description } = gameContentMessages;
    const { gameModData = [] } = this.props;

    // Only show question data for now...
    const dataToRender = gameModData.filter(
      gmd => gmd.key === "questionDataSet"
    );
    const questionDataSetData = dataToRender[0];

    console.log("###", gameModData);

    const downloadUrl = !questionDataSetData ? "" : getUrl(
      `/dev/download-custom-game-mod-data-xls/${
        questionDataSetData.id
      }?game_mod_build_id=${questionDataSetData.game_mod_build_id}`
    );

    return (
      <Card>
        <CardBlock>
          <Text as="h1" noMargins>
            {title}
          </Text>
          <Text as="h6">{description}</Text>

          <Box inline marginRight="1em">
            <form action={downloadUrl} method="post" target="_blank">
              <Button type="submit" primary>
                Download Spreadsheet
              </Button>
            </form>
          </Box>
          <FileUploadButton accept=".xlsx" onChange={this.handleFileChange}>
            Upload New Version
          </FileUploadButton>
          <Box marginTop="1em">
            <Table
              data={this.mapDataToHumanReadable(dataToRender, this.props.fields)}
              columns={columns}
              rowKey="custom-data-spreadsheet"
              title={title}
              hideTitle
            />
          </Box>
          <Box marginTop="1em">
            {/*<Link href="#">Learn more about Custom Game Data</Link>*/}
          </Box>
        </CardBlock>
      </Card>
    );
  }
}
